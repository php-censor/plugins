<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Common;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Plugin\Plugin;

/**
 * Git plugin.
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Git extends Plugin
{
    private $actions = [];

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'git';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        // Check if there are any actions to be run for the branch we're running on:
        if (!\array_key_exists($this->build->getBranch(), $this->actions)) {
            return true;
        }

        $success = true;
        foreach ($this->actions[$this->build->getBranch()] as $action => $options) {
            if (!$this->runAction($action, $options)) {
                $success = false;
                break;
            }
        }

        return $success;
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
            BuildInterface::STAGE_DEPLOY,
            BuildInterface::STAGE_SETUP,
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
        $this->actions = $this->options->all();
    }

    /**
     * Determine which action to run, and run it.
     *
     * @param string $action
     * @param array  $options
     *
     * @return bool
     */
    private function runAction(string $action, array $options = [])
    {
        switch ($action) {
            case 'merge':
                return $this->runMergeAction($options);

            case 'tag':
                return $this->runTagAction($options);

            case 'pull':
                return $this->runPullAction($options);

            case 'push':
                return $this->runPushAction($options);
        }


        return false;
    }

    /**
     * Handle a merge action.
     *
     * @param array $options
     *
     * @return bool
     */
    private function runMergeAction(array $options = []): bool
    {
        if (\array_key_exists('branch', $options)) {
            $cmd  = 'cd "%s" && git checkout %s && git merge "%s"';
            $path = $this->build->getBuildPath();

            return $this->commandExecutor->executeCommand($cmd, $path, $options['branch'], $this->build->getBranch());
        }

        return true;
    }

    /**
     * Handle a tag action.
     *
     * @param array $options
     *
     * @return bool
     */
    private function runTagAction(array $options = []): bool
    {
        $tagName = \date('Ymd-His');
        $message = \sprintf('Tag created by PHP Censor: %s', \date('Y-m-d H:i:s'));

        if (\array_key_exists('name', $options)) {
            $tagName = $this->variableInterpolator->interpolate($options['name']);
        }

        if (\array_key_exists('message', $options)) {
            $message = $this->variableInterpolator->interpolate($options['message']);
        }

        $cmd = 'git tag %s -m "%s"';
        return $this->commandExecutor->executeCommand($cmd, $tagName, $message);
    }

    /**
     * Handle a pull action.
     *
     * @param array $options
     *
     * @return bool
     */
    private function runPullAction(array $options = []): bool
    {
        $branch = $this->build->getBranch();
        $remote = 'origin';

        if (\array_key_exists('branch', $options)) {
            $branch = $this->variableInterpolator->interpolate($options['branch']);
        }

        if (\array_key_exists('remote', $options)) {
            $remote = $this->variableInterpolator->interpolate($options['remote']);
        }

        return $this->commandExecutor->executeCommand('git pull %s %s', $remote, $branch);
    }

    /**
     * Handle a push action.
     *
     * @param array $options
     *
     * @return bool
     */
    private function runPushAction(array $options = []): bool
    {
        $branch = $this->build->getBranch();
        $remote = 'origin';

        if (\array_key_exists('branch', $options)) {
            $branch = $this->variableInterpolator->interpolate($options['branch']);
        }

        if (\array_key_exists('remote', $options)) {
            $remote = $this->variableInterpolator->interpolate($options['remote']);
        }

        return $this->commandExecutor->executeCommand('git push %s %s', $remote, $branch);
    }
}
