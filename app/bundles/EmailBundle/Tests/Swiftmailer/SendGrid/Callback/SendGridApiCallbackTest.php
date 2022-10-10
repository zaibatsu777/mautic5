<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Swiftmailer\SendGrid\Callback;

use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\Swiftmailer\SendGrid\Callback\SendGridApiCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Symfony\Component\HttpFoundation\Request;

class SendGridApiCallbackTest extends \PHPUnit\Framework\TestCase
{
    public function testSupportedEvents(): void
    {
        $transportCallback   = $this->createMock(TransportCallback::class);
        $sendGridApiCallback = new SendGridApiCallback($transportCallback);
        $payload             = [
            [
                'email'         => 'example5@test.com',
                'timestamp'     => '1512130989',
                'smtp-id'       => '<14c5d75ce93.dfd.64b469@ismtpd-555>',
                'event'         => 'click',
                'category'      => 'cat facts',
                'sg_event_id'   => 'cnlAXAv_JrVIKBxfIbzJYA==',
                'sg_message_id' => '14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0',
                'useragent'     => 'Mozilla/4.0 [compatible; MSIE 6.1; Windows XP; .NET CLR 1.1.4322; .NET CLR 2.0.50727]',
                'ip'            => '255.255.255.255',
                'url'           => 'http://www.sendgrid.com/',
            ],
            [
                'email'         => 'example6@test.com',
                'timestamp'     => '1512130989',
                'smtp-id'       => '<14c5d75ce93.dfd.64b469@ismtpd-555>',
                'event'         => 'bounce',
                'category'      => 'cat facts',
                'sg_event_id'   => '0zPC-is_ZeC7f6XD7KNElw==',
                'sg_message_id' => '14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0',
                'reason'        => '500 unknown recipient',
                'status'        => '5.0.0',
            ],
            [
                'email'         => 'example7@test.com',
                'timestamp'     => '1512130989',
                'smtp-id'       => '<14c5d75ce93.dfd.64b469@ismtpd-555>',
                'event'         => 'dropped',
                'category'      => 'cat facts',
                'sg_event_id'   => 'vLeH071SCk_wqaw_ieKp2w==',
                'sg_message_id' => '14c5d75ce93.dfd.64b469.filter0001.16648.5515E0B88.0',
                'reason'        => 'Bounced Address',
                'status'        => '5.0.0',
            ],
            [
            ],
        ];

        $request = new Request(['query'], $payload);

        $transportCallback->expects($this->exactly(2))
            ->method('addFailureByAddress')
            ->withConsecutive(
                ['example6@test.com', '500 unknown recipient', DoNotContact::BOUNCED],
                ['example7@test.com', 'Bounced Address', DoNotContact::BOUNCED]
            );

        $sendGridApiCallback->processCallbackRequest($request);
    }
}
