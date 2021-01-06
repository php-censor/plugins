<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Plugins\Notification\EmailNotify\ViewFactoryInterface;
use PHPCensor\Plugins\Notification\EmailNotify\ViewInterface;

/**
 * EmailNotify Plugin - Provides simple email capability.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Steve Brazier <meadsteve@gmail.com>
 */
class EmailNotify extends Plugin
{
    /**
     * @var ViewFactoryInterface
     */
    private $viewFactory;

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'email_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $addresses = $this->getEmailAddresses();

        // Without some email addresses in the yml file then we can't do anything.
        if (\count($addresses) == 0) {
            return false;
        }

        $buildStatus = $this->build->isSuccessful() ? "Passing Build" : "Failing Build";
        $projectName = $this->project->getTitle();

        try {
            $view = $this->getMailTemplate();
        } catch (Exception $e) {
            $this->buildLogger->logWarning(
                \sprintf('Unknown mail template "%s", falling back to default.', $this->options['template'])
            );
            $view = $this->getDefaultMailTemplate();
        }

        $layout  = $this->viewFactory->createView('EmailNotify/layout');
        $layout->setVariables([
            'build'   => $this->build,
            'project' => $this->project,
            'content' => $view->render(),
        ]);

        $body = $layout->render();
        $layout->setVariables([
            'body' => $body,
        ]);

        $sendFailures = $this->sendSeparateEmails(
            $addresses,
            \sprintf("PHP Censor - %s - %s", $projectName, $buildStatus),
            $body
        );

        // This is a success if we've not failed to send anything.
        $this->buildLogger->logNormal(\sprintf('%d emails sent.', (\count($addresses) - $sendFailures)));
        $this->buildLogger->logNormal(\sprintf('%d emails failed to send.', $sendFailures));

        return ($sendFailures === 0);
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecute(string $stage, BuildInterface $build): bool
    {
        if (\in_array($stage, [
            BuildInterface::STAGE_BROKEN,
            BuildInterface::STAGE_COMPLETE,
            BuildInterface::STAGE_FAILURE,
            BuildInterface::STAGE_FIXED,
            BuildInterface::STAGE_SUCCESS,
        ], true)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initPluginSettings(): void
    {
        $this->viewFactory = $this->container->get(ViewFactoryInterface::class);
    }

    /**
     * @param string $toAddress Single address to send to
     * @param string[] $ccList
     * @param string $subject EmailNotify subject
     * @param string $body EmailNotify body
     *
     * @return int
     */
    private function sendEmail($toAddress, $ccList, $subject, $body)
    {
        $email = new EmailHelper(Config::getInstance());

        $email->setEmailTo($toAddress, $toAddress);
        $email->setSubject($subject);
        $email->setBody($body);
        $email->setHtml(true);

        if (\is_array($ccList) && \count($ccList)) {
            foreach ($ccList as $address) {
                $email->addCc($address, $address);
            }
        }

        return $email->send($this->builder);
    }

    /**
     * Send an email to a list of specified subjects.
     *
     * @param array  $toAddresses List of destination addresses for message.
     * @param string $subject     Mail subject
     * @param string $body        Mail body
     *
     * @return int number of failed messages
     */
    private function sendSeparateEmails(array $toAddresses, string $subject, string $body): int
    {
        $failures = 0;
        $ccList   = $this->getCcAddresses();

        foreach ($toAddresses as $address) {
            if (!$this->sendEmail($address, $ccList, $subject, $body)) {
                $failures++;
            }
        }

        return $failures;
    }

    /**
     * Get the list of email addresses to send to.
     *
     * @return array
     */
    private function getEmailAddresses(): array
    {
        $addresses = [];
        $committer = $this->build->getCommitterEmail();

        $this->buildLogger->logDebug(\sprintf("Committer email: '%s'", $committer));
        $this->buildLogger->logDebug(\sprintf(
            "Committer option: '%s'",
            (!empty($this->options['committer']) && $this->options['committer']) ? 'true' : 'false'
        ));

        if (!empty($this->options['committer']) && $this->options['committer']) {
            if ($committer) {
                $addresses[] = $committer;
            }
        }

        $this->buildLogger->logDebug(\sprintf(
            "Addresses option: '%s'",
            (!empty($this->options['addresses']) && \is_array($this->options['addresses'])) ? \implode(', ', $this->options['addresses']) : 'false'
        ));

        if (!empty($this->options['addresses']) && \is_array($this->options['addresses'])) {
            foreach ($this->options['addresses'] as $address) {
                $addresses[] = $address;
            }
        }

        $this->buildLogger->logDebug(\sprintf(
            "Default mailTo option: '%s'",
            !empty($this->options['default_mailto_address']) ? $this->options['default_mailto_address'] : 'false'
        ));

        if (empty($addresses) && !empty($this->options['default_mailto_address'])) {
            $addresses[] = $this->options['default_mailto_address'];
        }

        return \array_unique($addresses);
    }

    /**
     * Get the list of email addresses to CC.
     *
     * @return array
     */
    private function getCcAddresses(): array
    {
        $ccAddresses = [];
        if (isset($this->options['cc'])) {
            foreach ($this->options['cc'] as $address) {
                $ccAddresses[] = $address;
            }
        }

        return $ccAddresses;
    }

    /**
     * Get the mail template used to sent the mail.
     *
     * @return ViewInterface
     *
     * @throws Exception
     */
    private function getMailTemplate(): ViewInterface
    {
        if (isset($this->options['template'])) {
            return $this->viewFactory->createView('EmailNotify/' . $this->options['template']);
        }

        return $this->getDefaultMailTemplate();
    }

    /**
     * Get the default mail template.
     *
     * @return ViewInterface
     *
     * @throws Exception
     */
    private function getDefaultMailTemplate(): ViewInterface
    {
        $template = $this->build->isSuccessful() ? 'short' : 'long';

        return $this->viewFactory->createView('EmailNotify/' . $template);
    }
}
