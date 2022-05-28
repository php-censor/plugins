<?php

declare(strict_types=1);

namespace PHPCensor\Plugins\Notification;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Email\Email;
use PHPCensor\Common\Email\EmailSenderInterface;
use PHPCensor\Common\View\ViewFactoryInterface;
use PHPCensor\Common\View\ViewInterface;

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
    private ViewFactoryInterface $viewFactory;

    private bool $committer = false;

    private array $addresses = [];

    private string $defaultMailtoAddress = '';

    private array $cc = [];

    private string $template = '';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'email_notify';
    }

    /**
     * {@inheritDoc}
     */
    public function execute(): bool
    {
        $addresses = $this->getEmailAddresses();

        // Without some email addresses in the yml file then we can't do anything.
        if (\count($addresses) === 0) {
            return false;
        }

        $buildStatus = $this->build->isSuccessful() ? "Passing Build" : "Failing Build";
        $projectName = $this->project->getTitle();

        try {
            $view = $this->getMailTemplate();
        } catch (Exception $e) {
            $this->buildLogger->logWarning(
                \sprintf('Unknown mail template "%s", falling back to default.', (string)$this->options->get('template'))
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    protected function initPluginSettings(): void
    {
        $this->viewFactory = $this->container->get(ViewFactoryInterface::class);

        $this->committer = (bool)$this->options->get('committer', $this->committer);

        $this->defaultMailtoAddress = (string)$this->options->get('default_mailto_address', $this->defaultMailtoAddress);
        $this->template             = (string)$this->options->get('template', $this->template);

        $this->addresses = (array)$this->options->get('addresses', $this->addresses);
        $this->cc        = (array)$this->options->get('cc', $this->cc);
    }

    /**
     * @param string $toAddress Single address to send to
     * @param string[] $ccList
     * @param string $subject EmailNotify subject
     * @param string $body EmailNotify body
     */
    private function sendEmail(string $toAddress, array $ccList, string $subject, string $body): int
    {
        $email = new Email();

        $email->setEmailTo($toAddress);
        $email->setSubject($subject);
        $email->setBody($body);
        $email->setIsHtml(true);

        if ($ccList) {
            foreach ($ccList as $address) {
                $email->addCarbonCopyEmail($address);
            }
        }

        /** @var EmailSenderInterface $sender */
        $sender = $this->container->get(EmailSenderInterface::class);

        return $sender->send($email);
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
        foreach ($toAddresses as $address) {
            if (!$this->sendEmail($address, $this->cc, $subject, $body)) {
                $failures++;
            }
        }

        return $failures;
    }

    private function getEmailAddresses(): array
    {
        $addresses = [];
        $committer = $this->build->getCommitterEmail();

        $this->buildLogger->logDebug(\sprintf("Committer email: '%s'", $committer));
        $this->buildLogger->logDebug(\sprintf("Committer option: '%s'", $this->committer ? 'true' : 'false'));

        if ($this->committer && $committer) {
            $addresses[] = $committer;
        }

        $this->buildLogger->logDebug(
            \sprintf(
                "Addresses option: '%s'",
                ($this->addresses ? \implode(', ', $this->addresses) : 'false')
            )
        );

        if ($this->addresses) {
            foreach ($this->addresses as $address) {
                $addresses[] = $address;
            }
        }

        $this->buildLogger->logDebug(\sprintf(
            "Default mailTo option: '%s'",
            $this->defaultMailtoAddress ? $this->defaultMailtoAddress : 'false'
        ));

        if (empty($addresses) && $this->defaultMailtoAddress) {
            $addresses[] = $this->defaultMailtoAddress;
        }

        return \array_unique($addresses);
    }

    /**
     * Get the mail template used to sent the mail.
     *
     * @throws Exception
     */
    private function getMailTemplate(): ViewInterface
    {
        if ($this->template) {
            return $this->viewFactory->createView('EmailNotify/' . $this->template);
        }

        return $this->getDefaultMailTemplate();
    }

    /**
     * Get the default mail template.
     *
     * @throws Exception
     */
    private function getDefaultMailTemplate(): ViewInterface
    {
        $template = $this->build->isSuccessful() ? 'short' : 'long';

        return $this->viewFactory->createView('EmailNotify/' . $template);
    }
}
