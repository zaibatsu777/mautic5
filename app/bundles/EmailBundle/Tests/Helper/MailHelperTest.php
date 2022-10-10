<?php

namespace Mautic\EmailBundle\Tests\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\MonitoredEmail\Mailbox;
use Mautic\EmailBundle\Swiftmailer\Exception\BatchQueueMaxException;
use Mautic\EmailBundle\Swiftmailer\Spool\DelegatingSpool;
use Mautic\EmailBundle\Swiftmailer\Transport\SpoolTransport;
use Mautic\EmailBundle\Tests\Helper\Transport\BatchTransport;
use Mautic\EmailBundle\Tests\Helper\Transport\BcInterfaceTokenTransport;
use Mautic\EmailBundle\Tests\Helper\Transport\SmtpTransport;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Monolog\Logger;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Swift_Mailer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MailHelperTest extends TestCase
{
    /**
     * @var MauticFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockFactory;

    /**
     * @var SpoolTransport
     */
    private $spoolTransport;

    /**
     * @var \Swift_Events_EventDispatcher
     */
    private $swiftEventsDispatcher;

    /**
     * @var DelegatingSpool
     */
    private $delegatingSpool;

    /**
     * @var array
     */
    protected $contacts = [
        [
            'id'        => 1,
            'email'     => 'contact1@somewhere.com',
            'firstname' => 'Contact',
            'lastname'  => '1',
            'owner_id'  => 1,
        ],
        [
            'id'        => 2,
            'email'     => 'contact2@somewhere.com',
            'firstname' => 'Contact',
            'lastname'  => '2',
            'owner_id'  => 0,
        ],
        [
            'id'        => 3,
            'email'     => 'contact3@somewhere.com',
            'firstname' => 'Contact',
            'lastname'  => '3',
            'owner_id'  => 2,
        ],
        [
            'id'        => 4,
            'email'     => 'contact4@somewhere.com',
            'firstname' => 'Contact',
            'lastname'  => '4',
            'owner_id'  => 1,
        ],
    ];

    protected function setUp(): void
    {
        defined('MAUTIC_ENV') or define('MAUTIC_ENV', 'test');

        $this->mockFactory           = $this->createMock(MauticFactory::class);
        $this->swiftEventsDispatcher = $this->createMock(\Swift_Events_EventDispatcher::class);
        $this->delegatingSpool       = $this->createMock(DelegatingSpool::class);

        $this->spoolTransport = new SpoolTransport($this->swiftEventsDispatcher, $this->delegatingSpool);
    }

    public function testQueueModeThrowsExceptionWhenBatchLimitHit()
    {
        $this->expectException(BatchQueueMaxException::class);

        $mockFactory = $this->getMockBuilder(MauticFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFactory->method('getParameter')
            ->will(
                $this->returnValueMap(
                    [
                        ['mailer_return_path', false, null],
                        ['mailer_spool_type', false, 'memory'],
                    ]
                )
            );

        $swiftMailer = new Swift_Mailer(new BatchTransport());

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        // Enable queue mode
        $mailer->enableQueue();
        $mailer->addTo('somebody@somewhere.com');
        $mailer->addTo('somebodyelse@somewhere.com');
        $mailer->addTo('somebodyelse2@somewhere.com');
        $mailer->addTo('somebodyelse3@somewhere.com');
        $mailer->addTo('somebodyelse4@somewhere.com');
    }

    public function testQueueModeDisabledDoesNotThrowsExceptionWhenBatchLimitHit()
    {
        $mockFactory = $this->getMockBuilder(MauticFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFactory->method('getParameter')
            ->will(
                $this->returnValueMap(
                    [
                        ['mailer_return_path', false, null],
                        ['mailer_spool_type', false, 'memory'],
                    ]
                )
            );

        $swiftMailer = new Swift_Mailer(new BatchTransport());

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        // Enable queue mode
        try {
            $mailer->addTo('somebody@somewhere.com');
            $mailer->addTo('somebodyelse@somewhere.com');
        } catch (BatchQueueMaxException $exception) {
            $this->fail('BatchQueueMaxException thrown');
        }

        // Otherwise success
        $this->assertTrue(true);
    }

    public function testQueuedEmailFromOverride()
    {
        $mockFactory = $this->getMockFactory(false);

        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->enableQueue();

        $email = new Email();
        $email->setFromAddress('override@nowhere.com');
        $email->setFromName('Test');
        $email->setUseOwnerAsMailer(false);

        $mailer->setEmail($email);

        foreach ($this->contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }

        $mailer->flushQueue();
        $from = $mailer->message->getFrom();

        $this->assertTrue(array_key_exists('override@nowhere.com', $from));
        $this->assertTrue(1 === count($from));

        $mailer->reset();
        foreach ($this->contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }
        $mailer->flushQueue();
        $from = $mailer->message->getFrom();

        $this->assertTrue(array_key_exists('nobody@nowhere.com', $from));
        $this->assertTrue(1 === count($from));
    }

    public function testBatchMode()
    {
        $mockFactory = $this->getMockFactory(false);

        $transport   = new BatchTransport(true);
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->enableQueue();

        $email = new Email();
        $email->setSubject('Hello');
        $mailer->setEmail($email);

        $mailer->addTo($this->contacts[0]['email']);
        $mailer->setLead($this->contacts[0]);
        $mailer->queue();
        $mailer->flushQueue();
        $errors = $mailer->getErrors();
        $this->assertEmpty($errors['failures'], var_export($errors, true));

        $mailer->reset(false);
        $mailer->setEmail($email);
        $mailer->addTo($this->contacts[1]['email']);
        $mailer->setLead($this->contacts[1]);
        $mailer->queue();
        $mailer->flushQueue();
        $errors = $mailer->getErrors();
        $this->assertEmpty($errors['failures'], var_export($errors, true));
    }

    public function testQueuedOwnerAsMailer()
    {
        $mockFactory = $this->getMockFactory();

        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        $email = new Email();
        $email->setUseOwnerAsMailer(true);
        $mailer->setEmail($email);

        $mailer->enableQueue();

        $mailer->setSubject('Hello');

        foreach ($this->contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }

        $mailer->flushQueue([]);

        $this->assertEmpty($mailer->getErrors()['failures']);

        $fromAddresses = $transport->getFromAddresses();
        $metadatas     = $transport->getMetadatas();

        $this->assertEquals(3, count($fromAddresses));
        $this->assertEquals(3, count($metadatas));
        $this->assertEquals(['owner1@owner.com', 'nobody@nowhere.com', 'owner2@owner.com'], $fromAddresses);

        foreach ($metadatas as $key => $metadata) {
            $this->assertTrue(isset($metadata[$this->contacts[$key]['email']]));

            if (0 === $key) {
                // Should have two contacts
                $this->assertEquals(2, count($metadata));
                $this->assertTrue(isset($metadata['contact4@somewhere.com']));
            } else {
                $this->assertEquals(1, count($metadata));
            }

            // Check that signatures are valid
            if (1 === $key) {
                // There should not be a signature token because owner was not set and token events have not been dispatched
                $this->assertFalse(isset($metadata['tokens']['{signature}']));
            } else {
                $this->assertEquals($metadata[$this->contacts[$key]['email']]['tokens']['{signature}'], 'owner '.$this->contacts[$key]['owner_id']);

                if (0 === $key) {
                    // Ensure the last contact has the correct signature
                    $this->assertEquals($metadata['contact4@somewhere.com']['tokens']['{signature}'], 'owner '.$this->contacts[$key]['owner_id']);
                }
            }
        }

        // Validate that the message object only has the contacts for the last "from" group to ensure we aren't sending duplicates
        $this->assertEquals(['contact3@somewhere.com' => null], $mailer->message->getTo());
    }

    public function testMailAsOwnerWithEncodedCharactersInName()
    {
        $mockFactory = $this->getMockFactory();

        $transport   = new BatchTransport();
        $swiftMailer = new \Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body&#39;s Business']);
        $email  = new Email();
        $email->setUseOwnerAsMailer(true);

        $mailer->setEmail($email);
        $mailer->enableQueue();
        $mailer->enableQueue();

        $mailer->setSubject('Hello');

        $contacts                = $this->contacts;
        $contacts[3]['owner_id'] = 3;

        foreach ($contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }

        $mailer->flushQueue([]);

        $fromAddresses = $transport->getFromAddresses();
        $fromNames     = $transport->getFromNames();

        $this->assertEquals(4, count($fromAddresses));
        $this->assertEquals(4, count($fromNames));
        $this->assertEquals(['owner1@owner.com', 'nobody@nowhere.com', 'owner2@owner.com', 'owner3@owner.com'], $fromAddresses);
        $this->assertEquals([null, "No Body's Business", null, "John S'mith"], $fromNames);
    }

    public function testBatchIsEnabledWithBcTokenInterface()
    {
        $mockFactory = $this->getMockFactory();

        $transport   = new BcInterfaceTokenTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        $email = new Email();
        $email->setUseOwnerAsMailer(true);

        $mailer->setEmail($email);
        $mailer->enableQueue();

        $mailer->setSubject('Hello');

        foreach ($this->contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }

        $mailer->flushQueue([]);

        $this->assertEmpty($mailer->getErrors()['failures']);

        $fromAddresses = $transport->getFromAddresses();
        $metadatas     = $transport->getMetadatas();

        $this->assertEquals(3, count($fromAddresses));
        $this->assertEquals(3, count($metadatas));
    }

    public function testGlobalFromThatAllFromAddressesAreTheSame()
    {
        $mockFactory = $this->getMockFactory();

        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->enableQueue();

        $mailer->setSubject('Hello');
        $mailer->setFrom('override@owner.com');

        foreach ($this->contacts as $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->queue();
        }

        $mailer->flushQueue();

        $this->assertEmpty($mailer->getErrors()['failures']);

        $fromAddresses = $transport->getFromAddresses();

        $this->assertEquals(['override@owner.com'], array_unique($fromAddresses));
    }

    public function testStandardEmailFrom()
    {
        $mockFactory = $this->getMockFactory(true);
        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);
        $mailer      = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $email       = new Email();

        $email->setUseOwnerAsMailer(false);
        $email->setFromAddress('override@nowhere.com');
        $email->setFromName('Test');
        $mailer->setEmail($email);

        foreach ($this->contacts as $key => $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->setBody('{signature}');
            $mailer->send();
            $from = key($mailer->message->getFrom());
            $this->assertEquals('override@nowhere.com', $from);
        }
    }

    public function testStandardEmailReplyTo()
    {
        $mockFactory = $this->getMockFactory(true);
        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);
        $mailer      = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $email       = new Email();

        $mailer->setEmail($email);
        $replyTo = key((array) $mailer->message->getReplyTo());
        $this->assertEquals('nobody@nowhere.com', $replyTo);

        $email->setReplyToAddress('replytooverride@nowhere.com');
        $mailer->setEmail($email);
        $replyTo = key((array) $mailer->message->getReplyTo());
        $this->assertEquals('replytooverride@nowhere.com', $replyTo);
    }

    public function testEmailReplyToWithFromEmail(): void
    {
        $mockFactory = $this->getMockFactory(true);
        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);
        $mailer      = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $email       = new Email();

        // From address is set
        $email->setFromAddress('from@nowhere.com');
        $mailer->setEmail($email);
        $replyTo = key((array) $mailer->message->getReplyTo());
        // Expect from address in reply to
        $this->assertEquals('from@nowhere.com', $replyTo);
    }

    public function testEmailReplyToWithFromAndGlobalEmail(): void
    {
        $parameterMap = [
            ['mailer_reply_to_email', false, 'admin@mautic.com'],
        ];
        $factoryMock = $this->getMockFactory(true, $parameterMap);
        $transport   = new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);
        $mailer      = new MailHelper($factoryMock, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $email       = new Email();

        // From address is set
        $email->setFromAddress('from@nowhere.com');
        $mailer->setEmail($email);
        $replyTo = key((array) $mailer->message->getReplyTo());
        // Expect from address in reply to
        $this->assertEquals('admin@mautic.com', $replyTo);
    }

    public function testStandardOwnerAsMailer()
    {
        $mockFactory = $this->getMockFactory();

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        $email = new Email();
        $mailer->setEmail($email);
        $email->setUseOwnerAsMailer(true);

        $mailer->setBody('{signature}');

        foreach ($this->contacts as $key => $contact) {
            $mailer->addTo($contact['email']);
            $mailer->setLead($contact);
            $mailer->send();

            $body = $mailer->message->getBody();
            $from = key($mailer->message->getFrom());

            if ($contact['owner_id']) {
                $this->assertEquals('owner'.$contact['owner_id'].'@owner.com', $from);
                $this->assertEquals('owner '.$contact['owner_id'], $body);
            } else {
                $this->assertEquals('nobody@nowhere.com', $from);
                $this->assertEquals('{signature}', $body);
            }
        }
    }

    public function testValidateValidEmails()
    {
        $helper    = $this->mockEmptyMailHelper();
        $addresses = [
            'john@doe.com',
            'john@doe.email',
            'john.doe@email.com',
            'john+doe@email.com',
            'john@doe.whatevertldtheycomewithinthefuture',
        ];

        foreach ($addresses as $address) {
            // will throw Swift_RfcComplianceException if it will find the address invalid
            $this->assertNull($helper::validateEmail($address));
        }
    }

    public function testValidateEmailWithoutTld()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('john@doe');
    }

    public function testValidateEmailWithSpaceInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo hn@doe.email');
    }

    public function testValidateEmailWithCaretInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo^hn@doe.email');
    }

    public function testValidateEmailWithApostropheInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo\'hn@doe.email');
    }

    public function testValidateEmailWithSemicolonInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo;hn@doe.email');
    }

    public function testValidateEmailWithAmpersandInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo&hn@doe.email');
    }

    public function testValidateEmailWithStarInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo*hn@doe.email');
    }

    public function testValidateEmailWithPercentInIt()
    {
        $helper = $this->mockEmptyMailHelper();
        $this->expectException(\Swift_RfcComplianceException::class);
        $helper::validateEmail('jo%hn@doe.email');
    }

    public function testQueueModeIsReset()
    {
        $contacts   = $this->contacts;
        $contacts[] = [
            'id'        => 5,
            'email'     => 'contact5@somewhere.com',
            'firstname' => 'Contact',
            'lastname'  => '5',
            'owner_id'  => 1,
        ];

        $helper = $this->mockEmptyMailHelper(false);

        $helper->enableQueue();

        $helper->addTo($contacts[0]['email']);
        $helper->addTo($contacts[1]['email']);
        $helper->addTo($contacts[2]['email']);
        $helper->addTo($contacts[3]['email']);

        $exceptionCaught = true;
        try {
            $helper->addTo($contacts[4]['email']);
            $exceptionCaught = false;
        } catch (BatchQueueMaxException $exception) {
        }

        if (!$exceptionCaught) {
            $this->fail('BatchQueueMaxException should have been thrown');
        }

        // Reset which should now reset qeue mode so that each to address is accepted
        $helper->reset();

        try {
            foreach ($contacts as $contact) {
                $helper->addTo($contact['email']);
            }
        } catch (BatchQueueMaxException $exception) {
            $this->fail('Queue mode was not reset');
        }

        $to = $helper->message->getTo();

        $this->assertEquals(
            [
                'contact1@somewhere.com' => null,
                'contact2@somewhere.com' => null,
                'contact3@somewhere.com' => null,
                'contact4@somewhere.com' => null,
                'contact5@somewhere.com' => null,
            ],
            $to
        );
    }

    public function testGlobalHeadersAreSet()
    {
        $parameterMap = [
            ['mailer_custom_headers', [], ['X-Mautic-Test' => 'test', 'X-Mautic-Test2' => 'test']],
        ];
        $mockFactory = $this->getMockFactory(true, $parameterMap);

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->setBody('{signature}');
        $mailer->addTo($this->contacts[0]['email']);
        $mailer->send();

        $customHeadersFounds = [];

        /** @var \Swift_Mime_Headers_ParameterizedHeader[] $headers */
        $headers = $mailer->message->getHeaders()->getAll();
        foreach ($headers as $header) {
            if (false !== strpos($header->getFieldName(), 'X-Mautic-Test')) {
                $customHeadersFounds[] = $header->getFieldName();

                $this->assertEquals('test', $header->getValue());
            }
        }

        $this->assertCount(2, $customHeadersFounds);
    }

    public function testGlobalHeadersAreIgnoredIfEmailEntityIsSet()
    {
        $parameterMap = [
            ['mailer_custom_headers', [], ['X-Mautic-Test' => 'test', 'X-Mautic-Test2' => 'test']],
        ];
        $mockFactory = $this->getMockFactory(true, $parameterMap);

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->addTo($this->contacts[0]['email']);

        $email = new Email();
        $email->setSubject('Test');
        $email->setCustomHtml('{signature}');
        $mailer->setEmail($email);
        $mailer->send();

        /** @var \Swift_Mime_Headers_ParameterizedHeader[] $headers */
        $headers = $mailer->message->getHeaders()->getAll();
        foreach ($headers as $header) {
            $this->assertFalse(strpos($header->getFieldName(), 'X-Mautic-Test'), 'System headers were not supposed to be set');
        }
    }

    public function testEmailHeadersAreSet()
    {
        $parameterMap = [
            ['mailer_custom_headers', [], ['X-Mautic-Test' => 'test', 'X-Mautic-Test2' => 'test']],
        ];
        $mockFactory = $this->getMockFactory(true, $parameterMap);

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->addTo($this->contacts[0]['email']);

        $email = new Email();
        $email->setSubject('Test');
        $email->setCustomHtml('{signature}');
        $email->setHeaders(['X-Mautic-Test3' => 'test2', 'X-Mautic-Test4' => 'test2']);
        $mailer->setEmail($email);
        $mailer->send();

        $customHeadersFounds = [];

        /** @var \Swift_Mime_Headers_ParameterizedHeader[] $headers */
        $headers = $mailer->message->getHeaders()->getAll();
        foreach ($headers as $header) {
            if (false !== strpos($header->getFieldName(), 'X-Mautic-Test')) {
                $customHeadersFounds[] = $header->getFieldName();

                $this->assertEquals('test2', $header->getValue());
            }
        }

        $this->assertCount(2, $customHeadersFounds);
    }

    protected function mockEmptyMailHelper($useSmtp = true)
    {
        $mockFactory = $this->getMockFactory();
        $transport   = ($useSmtp) ? new SmtpTransport() : new BatchTransport();
        $swiftMailer = new Swift_Mailer($transport);

        return new MailHelper($mockFactory, $swiftMailer);
    }

    protected function getMockFactory($mailIsOwner = true, $parameterMap = [])
    {
        $mockLeadRepository = $this->getMockBuilder(LeadRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockLeadRepository->method('getLeadOwner')
            ->will(
                $this->returnValueMap(
                    [
                        [1, ['id' => 1, 'email' => 'owner1@owner.com', 'first_name' => '', 'last_name' => '', 'signature' => 'owner 1']],
                        [2, ['id' => 2, 'email' => 'owner2@owner.com', 'first_name' => '', 'last_name' => '', 'signature' => 'owner 2']],
                        [3, ['id' => 3, 'email' => 'owner3@owner.com', 'first_name' => 'John', 'last_name' => 'S&#39;mith', 'signature' => 'owner 2']],
                    ]
                )
            );

        $mockLeadModel = $this->getMockBuilder(LeadModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockLeadModel->method('getRepository')
            ->willReturn($mockLeadRepository);

        $mockFactory = $this->getMockBuilder(MauticFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $parameterMap = array_merge(
            [
                ['mailer_return_path', false, null],
                ['mailer_spool_type', false, 'memory'],
                ['mailer_is_owner', false, $mailIsOwner],
            ],
            $parameterMap
        );

        $mockFactory->method('getParameter')
            ->will(
                $this->returnValueMap($parameterMap)
            );
        $mockFactory->method('getModel')
            ->will(
                $this->returnValueMap(
                    [
                        ['lead', $mockLeadModel],
                    ]
                )
            );

        $mockLogger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFactory->method('getLogger')
            ->willReturn($mockLogger);

        $mockMailboxHelper = $this->getMockBuilder(Mailbox::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMailboxHelper->method('isConfigured')
            ->willReturn(false);

        $mockFactory->method('getHelper')
            ->will(
                $this->returnValueMap(
                    [
                        ['mailbox', $mockMailboxHelper],
                    ]
                )
            );

        return $mockFactory;
    }

    public function testArrayOfAddressesAreRemappedIntoEmailToNameKeyValuePair()
    {
        $mockFactory = $this->getMockBuilder(MauticFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFactory->method('getParameter')
            ->will(
                $this->returnValueMap(
                    [
                        ['mailer_return_path', false, null],
                        ['mailer_spool_type', false, 'memory'],
                    ]
                )
            );

        $swiftMailer = new Swift_Mailer(new SmtpTransport());

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);

        $mailer->setTo(['sombody@somewhere.com', 'sombodyelse@somewhere.com'], 'test');

        $this->assertEquals(
            [
                'sombody@somewhere.com'     => 'test',
                'sombodyelse@somewhere.com' => 'test',
            ],
            $mailer->message->getTo()
        );
    }

    public function testThatTokenizationIsEnabledIfTransportSupportsTokenization()
    {
        $swiftMailer = new Swift_Mailer($this->spoolTransport);
        $this->delegatingSpool->expects($this->once())
            ->method('isTokenizationEnabled')
            ->willReturn(true);

        $mailer = new MailHelper($this->mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $this->assertTrue($mailer->inTokenizationMode());
    }

    public function testThatTokenizationIsDisabledIfTransportDoesnotSupportTokenization()
    {
        $swiftMailer = new Swift_Mailer($this->spoolTransport);
        $this->delegatingSpool->expects($this->once())
            ->method('isTokenizationEnabled')
            ->willReturn(false);

        $mailer = new MailHelper($this->mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $this->assertFalse($mailer->inTokenizationMode());
    }

    public function testImagesEmbeddedOnSend(): void
    {
        $unsubscribeUrl   = '/unsubscribe';
        $trackingPixelUrl = '/tracking.gif';
        $parameterMap     = [
            ['mailer_convert_embed_images', false, true],
            ['mailer_append_tracking_pixel', false, true],
        ];
        $mockFactory = $this->getMockFactory(true, $parameterMap);

        $router          = $this->createMock(UrlGeneratorInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockFactory->expects(self::once())
            ->method('getDispatcher')
            ->willReturn($eventDispatcher);
        $mockFactory->expects(self::exactly(3))
            ->method('getRouter')
            ->willReturn($router);

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $mailer = new MailHelper($mockFactory, $swiftMailer, ['nobody@nowhere.com' => 'No Body']);
        $mailer->addTo($this->contacts[0]['email']);
        $mailer->setIdHash();

        $initialHtml = 'Text <a href="https://mautic.com">Mautic</a> <img src="{ token }" /> <img src="https://mautic.com/contact/{ token2 }/avatar.png"/> <img src="/tmp/fake.jpg">';
        $trackedHtml = $initialHtml.'<img height="1" width="1" src="{tracking_pixel}" alt="" />';

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new EmailSendEvent($mailer), EmailEvents::EMAIL_ON_SEND)
            ->willReturnCallback(static function (EmailSendEvent $event, string $eventName): object {
                $event->addToken('{ token }', 'https://mautic.com/image.gif');

                return $event;
            });

        $router->expects(self::exactly(3))
            ->method('generate')
            ->withConsecutive([
                'mautic_email_unsubscribe', ['idHash' => $mailer->getIdHash()], UrlGeneratorInterface::ABSOLUTE_URL,
            ], [
                'mautic_email_tracker', ['idHash' => $mailer->getIdHash()], UrlGeneratorInterface::ABSOLUTE_URL,
            ], [
                'mautic_email_tracker', ['idHash' => $mailer->getIdHash()], UrlGeneratorInterface::ABSOLUTE_URL,
            ])
            ->willReturnOnConsecutiveCalls($unsubscribeUrl, $trackingPixelUrl, $trackingPixelUrl);

        $email = new Email();
        $email->setSubject('Test');
        $email->setCustomHtml($initialHtml);
        $mailer->setEmail($email);

        Assert::assertNull($mailer->message->getBody());
        Assert::assertSame($trackedHtml, $mailer->getBody());

        $mailer->send(true);

        Assert::assertMatchesRegularExpression(
            '~Text <a href="https://mautic.com">Mautic</a> <img src="cid:[a-zA-Z0-9]+@swift.generated" /> <img src="cid:[a-zA-Z0-9]+@swift.generated"/> <img src="cid:[a-zA-Z0-9]+@swift.generated"><img height="1" width="1" src="/tracking.gif" alt="" />~',
            $mailer->message->getBody()
        );
        Assert::assertSame($trackedHtml, $mailer->getBody());
    }
}
