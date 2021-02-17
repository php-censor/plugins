<?php

declare(strict_types = 1);

namespace PHPCensor\Plugins\Notification;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Build\BuildMetaInterface;
use PHPCensor\Common\Exception\Exception;
use PHPCensor\Common\Plugin\Plugin;
use PHPCensor\Common\Plugin\Plugin\ParameterBag;
use PHPCensor\Common\Repository\BuildErrorRepositoryInterface;
use PHPCensor\Common\Repository\BuildMetaRepositoryInterface;
use PHPCensor\Common\Repository\BuildRepositoryInterface;
use PHPCensor\Plugins\CodeQuality\Pdepend;
use PHPCensor\Plugins\Notification\BitbucketNotify\PluginResult;
use PHPCensor\Plugins\Testing\PhpUnit;
use Psr\Http\Message\ResponseInterface;

/**
 * BitbucketNotify Plugin
 *
 * @package    PHP Censor
 * @subpackage Plugins
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 * @author Eugen Ganshorn  <eugen.ganshorn@check24.de>
 */
class BitbucketNotify extends Plugin
{
    /**
     * @var string
     */
    private string $url = '';

    /**
     * @var string
     */
    private string $authToken = '';

    /**
     * @var string
     */
    private string $projectKey = '';

    /**
     * @var string
     */
    private string $repositorySlug = '';

    /**
     * @var bool
     */
    private bool $createTaskPerFail = true;

    /**
     * @var bool
     */
    private bool $createTaskIfFail = true;

    /**
     * @var bool
     */
    private bool $updateBuild = false;

    /**
     * @var string
     */
    private string $message = '';

    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'bitbucket_notify';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): bool
    {
        $pullRequestId = $this->findPullRequestsByBranch();
        $targetBranch  = $this->getTargetBranchForPullRequest($pullRequestId);
        $plugins       = $this->prepareResult($targetBranch);
        $message       = $this->reportGenerator($this->buildResultComparator($plugins));
        if (!empty($message)) {
            $commentId = $this->createCommentInPullRequest($pullRequestId, $message);

            if ($this->createTaskIfFail) {
                $this->createTaskForCommentInPullRequest($commentId, 'pls fix php-censor report');
            }

            if ($this->createTaskPerFail) {
                foreach ($plugins as $plugin) {
                    $taskDescription = $plugin->generateTaskDescription();
                    if (!empty($taskDescription)) {
                        $this->createTaskForCommentInPullRequest($commentId, $taskDescription);
                    }
                }
            }
        }

        if ($this->updateBuild) {
            $this->updateBuild();
        }

        return true;
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
        $this->url            = (string)$this->options->get('url', $this->url);
        $this->message        = (string)$this->options->get('message', $this->message);
        $this->authToken      = (string)$this->options->get('auth_token', $this->authToken);
        $this->projectKey     = (string)$this->options->get('project_key', $this->projectKey);
        $this->repositorySlug = (string)$this->options->get('repository_slug', $this->repositorySlug);

        $this->createTaskPerFail = (bool)$this->options->get('create_task_per_fail', $this->createTaskPerFail);
        $this->createTaskIfFail  = (bool)$this->options->get('create_task_if_fail', $this->createTaskIfFail);
        $this->updateBuild       = (bool)$this->options->get('update_build', $this->updateBuild);

        if (empty($this->message)) {
            $this->message = '## PHP CENSOR Report' . PHP_EOL;
            $this->message .= '```' . PHP_EOL;
            $this->message .= '%STATS%' . PHP_EOL;
            $this->message .= '```' . PHP_EOL;
            $this->message .= '%BUILD_LINK%?is_new=only_new#errors' . PHP_EOL . PHP_EOL;

            $projectConfig    = $this->project->getConfig();
            $testOptionsArray = [];
            if (!empty($projectConfig['test'])) {
                $testOptionsArray = $projectConfig['test'];
            }

            $testSettings = new ParameterBag($testOptionsArray);
            if ($testSettings->has(PhpUnit::getName())) {
                $this->message .= $this->getArtifactLink('index.html') . PHP_EOL;
            }

            if ($testSettings->has(Pdepend::getName())) {
                $summary = $this->getArtifactLink('summary.xml');
                $chart   = $this->getArtifactLink('chart.svg');
                $pyramid = $this->getArtifactLink('pyramid.svg');

                $this->message .= \sprintf('![Chart](%s "Pdepend Chart")', $chart);
                $this->message .= \sprintf('![Pyramid](%s "Pdepend Pyramid")', $pyramid) . PHP_EOL;
                $this->message .= $summary . PHP_EOL;
            }
        }

        if (empty($this->url) ||
            empty($this->authToken) ||
            empty($this->projectKey) ||
            empty($this->repositorySlug)
        ) {
            throw new Exception('Please define the "url", "auth_token", "project_key" and "repository_slug" for bitbucket plugin!');
        }
    }

    /**
     * @return int
     *
     * @throws GuzzleException
     */
    private function findPullRequestsByBranch(): int
    {
        $endpoint = \sprintf('/projects/%s/repos/%s/pull-requests', $this->projectKey, $this->repositorySlug);
        $response = $this->apiRequest($endpoint)->getBody();
        $response = \json_decode($response, true);

        foreach ($response['values'] as $pullRequest) {
            if ($pullRequest['fromRef']['displayId'] === $this->build->getBranch()) {
                return (int)$pullRequest['id'];
            }
        }

        return 0;
    }

    /**
     * @param int $pullRequestId
     *
     * @return string
     *
     * @throws GuzzleException
     */
    private function getTargetBranchForPullRequest(int $pullRequestId): string
    {
        $endpoint = \sprintf(
            '/projects/%s/repos/%s/pull-requests/%d',
            $this->projectKey,
            $this->repositorySlug,
            $pullRequestId
        );

        $response = $this->apiRequest($endpoint)->getBody();
        $response = \json_decode($response, true);

        return $response['toRef']['displayId'];
    }

    /**
     * @param int    $pullRequestId
     * @param string $message
     *
     * @return int
     *
     * @throws GuzzleException
     */
    private function createCommentInPullRequest(int $pullRequestId, string $message): int
    {
        $endpoint = \sprintf(
            '/projects/%s/repos/%s/pull-requests/%s/comments',
            $this->projectKey,
            $this->repositorySlug,
            $pullRequestId
        );

        $response = $this->apiRequest($endpoint, 'post', ['text' => $message])->getBody();
        $response = \json_decode($response, true);

        return (int)$response['id'];
    }

    /**
     * @param int    $commentId
     * @param string $message
     *
     * @throws GuzzleException
     */
    private function createTaskForCommentInPullRequest(int $commentId, string $message): void
    {
        $this->apiRequest('/tasks', 'post', [
            'anchor' => [
                'id'   => $commentId,
                'type' => 'COMMENT',
            ],
            'text' => $message,
        ]);
    }

    private function updateBuild(): void
    {
        $endpoint = \sprintf(
            '/commits/%s',
            $this->build->getCommitId()
        );

        switch ($this->build->getStatus()) {
            case BuildInterface::STATUS_SUCCESS:
                $state = 'SUCCESSFUL';

                break;
            case BuildInterface::STATUS_FAILED:
                $state = 'FAILED';

                break;
            default:
                $state = 'INPROGRESS';
        }

        $this->buildStatusRequest($endpoint, 'post', [
            'state'       => $state,
            'key'         => 'php-censor',
            'name'        => 'PHP Censor',
            'url'         => $this->variableInterpolator->interpolate('%BUILD_LINK%'),
            'description' => '',
        ]);
    }

    /**
     * @param string $targetBranch
     *
     * @return PluginResult[]
     */
    private function prepareResult(string $targetBranch): array
    {
        /** @var BuildErrorRepositoryInterface $buildErrorRepository */
        $buildErrorRepository = $this->container->get(BuildErrorRepositoryInterface::class);

        $lastBuildId            = $this->findLatestBuild($targetBranch);
        $targetBranchBuildStats = [];
        if ($lastBuildId) {
            $targetBranchBuildStats = $buildErrorRepository->getErrorsCountPerPluginByBuildId($lastBuildId);
        }

        $currentBranchBuildStats = $buildErrorRepository->getErrorsCountPerPluginByBuildId($this->build->getId());
        if (empty($targetBranchBuildStats) && empty($currentBranchBuildStats)) {
            return [];
        }

        $plugins = \array_unique(
            \array_merge(
                \array_keys($targetBranchBuildStats),
                \array_keys($currentBranchBuildStats)
            )
        );
        \sort($plugins);

        $result = [];
        foreach ($plugins as $plugin) {
            $result[] = new PluginResult(
                $plugin,
                isset($targetBranchBuildStats[$plugin]) ? $targetBranchBuildStats[$plugin] : 0,
                isset($currentBranchBuildStats[$plugin]) ? $currentBranchBuildStats[$plugin] : 0
            );
        }

        $result[] = $this->getPhpUnitCoverage($targetBranch);

        return $result;
    }

    /**
     * @param string $targetBranch
     *
     * @return PluginResult
     */
    private function getPhpUnitCoverage(string $targetBranch): PluginResult
    {
        /** @var BuildMetaRepositoryInterface $buildMetaRepository */
        $buildMetaRepository = $this->container->get(BuildMetaRepositoryInterface::class);

        $latestTargetBuildId  = $this->findLatestBuild($targetBranch);

        $lastBuildId    = $this->findLatestBuild($targetBranch);
        $targetMetaData = [];
        if ($lastBuildId) {
            $targetMetaData = $buildMetaRepository->getOneByBuildIdAndPluginAndKey(
                $lastBuildId,
                PhpUnit::getName(),
                BuildMetaInterface::KEY_COVERAGE
            );
        }

        $currentMetaData = $buildMetaRepository->getOneByBuildIdAndPluginAndKey(
            $this->build->getId(),
            PhpUnit::getName(),
            BuildMetaInterface::KEY_COVERAGE
        );

        $targetBranchCoverage = [];
        if ($latestTargetBuildId && $targetMetaData) {
            $targetBranchCoverage = \json_decode($targetMetaData->getValue(), true);
        }

        $currentBranchCoverage = [];
        if (!\is_null($currentMetaData)) {
            $currentBranchCoverage = \json_decode($currentMetaData->getValue(), true);
        }

        return new PluginResult(
            PhpUnit::getName() . '-' . BuildMetaInterface::KEY_COVERAGE,
            isset($targetBranchCoverage['lines']) ? $targetBranchCoverage['lines'] : 0,
            isset($currentBranchCoverage['lines']) ? $currentBranchCoverage['lines'] : 0
        );
    }

    /**
     * @param PluginResult[] $plugins
     *
     * @return array
     */
    private function buildResultComparator(array $plugins): array
    {
        $maxPluginNameLength = 20;
        if (!empty($plugins)) {
            $maxPluginNameLength = \max(\array_map('strlen', $plugins));
        }

        $lines = [];
        foreach ($plugins as $plugin) {
            $lines[] = $plugin->generateFormattedOutput($maxPluginNameLength);
        }

        return $lines;
    }

    /**
     * @param array $stats
     *
     * @return string
     */
    private function reportGenerator(array $stats): string
    {
        $statsString = \trim(\implode(PHP_EOL, $stats));
        if (empty($stats)) {
            $statsString = 'no changes between your branch and target branch';
        }

        $message = \str_replace(['%STATS%'], [$statsString], $this->message);

        return $this->variableInterpolator->interpolate($message);
    }

    /**
     * @param string $branchName
     *
     * @return int|null
     *
     */
    private function findLatestBuild(string $branchName): ?int
    {
        /** @var BuildRepositoryInterface $buildRepository */
        $buildRepository = $this->container->get(BuildRepositoryInterface::class);

        $build = $buildRepository->getLatestByProjectAndBranch($this->project->getId(), $branchName);

        return ($build !== null) ? $build->getId() : null;
    }

    /**
     * @param string     $endpoint
     * @param string     $method
     * @param array|null $jsonBody
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    private function buildStatusRequest(string $endpoint, string $method = 'get', ?array $jsonBody = null): ResponseInterface
    {
        return $this->request($this->url . '/rest/build-status/1.0' . $endpoint, $method, $jsonBody);
    }

    /**
     * @param string     $endpoint
     * @param string     $method
     * @param array|null $jsonBody
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    private function apiRequest(string $endpoint, string $method = 'get', array $jsonBody = null): ResponseInterface
    {
        return $this->request($this->url . '/rest/api/1.0' . $endpoint, $method, $jsonBody);
    }

    /**
     * @param string     $endpoint
     * @param string     $method
     * @param array|null $jsonBody
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    private function request(string $endpoint, string $method = 'get', array $jsonBody = null): ResponseInterface
    {
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->authToken]];
        $jsonBody !== null && $options['json'] = $jsonBody;

        $httpClient = new HttpClient();

        return $httpClient->request($method, $endpoint, $options);
    }
}
