<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Decorates the original AccessDecisionManager class to log information
 * about the security voters and the decisions made by them.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @internal
 */
class TraceableAccessDecisionManager implements AccessDecisionManagerInterface
{
    private $manager;
    private $strategy;
    private $voters = [];
    private $decisionLog = []; // All decision logs
    private $currentLog = [];  // Logs being filled in

    public function __construct(AccessDecisionManagerInterface $manager)
    {
        $this->manager = $manager;

        if ($this->manager instanceof AccessDecisionManager) {
            // The strategy and voters are stored in a private properties of the decorated service
            $reflection = new \ReflectionProperty(AccessDecisionManager::class, 'strategy');
            $reflection->setAccessible(true);
            $this->strategy = $reflection->getValue($manager);
            $reflection = new \ReflectionProperty(AccessDecisionManager::class, 'voters');
            $reflection->setAccessible(true);
            $this->voters = $reflection->getValue($manager);
        }
    }

    public function getDecision(TokenInterface $token, array $attributes, object $object = null): AccessDecision
    {
        $currentDecisionLog = [
            'attributes' => $attributes,
            'object' => $object,
            'voterDetails' => [],
        ];

        $this->currentLog[] = &$currentDecisionLog;

        $result = $this->manager->getDecision($token, $attributes, $object);

        $currentDecisionLog['result'] = $result;

        $this->decisionLog[] = array_pop($this->currentLog); // Using a stack since getDecision can be called by voters

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $allowMultipleAttributes Whether to allow passing multiple values to the $attributes array
     *
     * @deprecated since Symfony 5.4, use {@see getDecision()} instead.
     */
    public function decide(TokenInterface $token, array $attributes, $object = null/*, bool $allowMultipleAttributes = false*/): bool
    {
        trigger_deprecation('symfony/security-core', '5.4', 'Method "%s::decide()" has been deprecated, use "%s::getDecision()" instead.', __CLASS__, __CLASS__);

        $currentDecisionLog = [
            'attributes' => $attributes,
            'object' => $object,
            'voterDetails' => [],
        ];

        $this->currentLog[] = &$currentDecisionLog;

        $result = $this->manager->decide($token, $attributes, $object, 3 < \func_num_args() && func_get_arg(3));

        $currentDecisionLog['result'] = $result;

        $this->decisionLog[] = array_pop($this->currentLog); // Using a stack since decide can be called by voters

        return $result;
    }

    /**
     * Adds voter vote and class to the voter details.
     *
     * @param array    $attributes attributes used for the vote
     * @param int|Vote $vote       vote of the voter
     */
    public function addVoterVote(VoterInterface $voter, array $attributes, $vote)
    {
        if (!$vote instanceof Vote) {
            trigger_deprecation('symfony/security-core', '5.4', 'Passing an int as the third argument to "%s::addVoterVote()" is deprecated, pass an instance of "%s" instead.', __CLASS__, Vote::class);
            $vote = new Vote($vote);
        }

        $currentLogIndex = \count($this->currentLog) - 1;
        $this->currentLog[$currentLogIndex]['voterDetails'][] = [
            'voter' => $voter,
            'attributes' => $attributes,
            'vote' => $vote,
        ];
    }

    public function getStrategy(): string
    {
        // The $strategy property is misleading because it stores the name of its
        // method (e.g. 'decideAffirmative') instead of the original strategy name
        // (e.g. 'affirmative')
        return null === $this->strategy ? '-' : strtolower(substr($this->strategy, 6));
    }

    /**
     * @return iterable|VoterInterface[]
     */
    public function getVoters(): iterable
    {
        return $this->voters;
    }

    public function getDecisionLog(): array
    {
        return $this->decisionLog;
    }
}

if (!class_exists(DebugAccessDecisionManager::class, false)) {
    class_alias(TraceableAccessDecisionManager::class, DebugAccessDecisionManager::class);
}
