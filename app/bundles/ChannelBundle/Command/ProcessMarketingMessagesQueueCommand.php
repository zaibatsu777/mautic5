<?php

namespace Mautic\ChannelBundle\Command;

use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\PathsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcessMarketingMessagesQueueCommand extends ModeratedCommand
{
    private TranslatorInterface $translator;
    private MessageQueueModel $messageQueueModel;

    public function __construct(TranslatorInterface $translator, MessageQueueModel $messageQueueModel, PathsHelper $pathsHelper)
    {
        $this->translator        = $translator;
        $this->messageQueueModel = $messageQueueModel;

        parent::__construct($pathsHelper);
    }

    protected function configure()
    {
        $this
            ->setName('mautic:messages:send')
            ->setAliases(
                [
                    'mautic:campaigns:messagequeue',
                    'mautic:campaigns:messages',
                ]
            )
            ->setDescription('Process sending of messages queue.')
            ->addOption(
                '--channel',
                '-c',
                InputOption::VALUE_OPTIONAL,
                'Channel to use for sending messages i.e. email, sms.',
                null
            )
            ->addOption('--channel-id', '-i', InputOption::VALUE_REQUIRED, 'The ID of the message i.e. email ID, sms ID.')
            ->addOption('--message-id', '-m', InputOption::VALUE_REQUIRED, 'ID of a specific queued message');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $processed  = 0;
        $channel    = $input->getOption('channel');
        $channelId  = $input->getOption('channel-id');
        $messageId  = $input->getOption('message-id');
        $key        = $channel.$channelId.$messageId;

        if (!$this->checkRunStatus($input, $output, $key)) {
            return 0;
        }

        $output->writeln('<info>'.$this->translator->trans('mautic.campaign.command.process.messages').'</info>');

        if ($messageId) {
            if ($message = $this->messageQueueModel->getEntity($messageId)) {
                $processed = intval($this->messageQueueModel->processMessageQueue($message));
            }
        } else {
            $processed = intval($this->messageQueueModel->sendMessages($channel, $channelId));
        }

        $output->writeln('<comment>'.$this->translator->trans('mautic.campaign.command.messages.sent', ['%events%' => $processed]).'</comment>'."\n");

        $this->completeRun();

        return 0;
    }
}
