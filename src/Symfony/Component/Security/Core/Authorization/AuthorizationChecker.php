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

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * AuthorizationChecker is the main authorization point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthorizationChecker implements AuthorizationCheckerInterface
{
    private $tokenStorage;
    private $accessDecisionManager;
    private $authenticationManager;
    private $alwaysAuthenticate;
    private $exceptionOnNoToken;

    public function __construct(TokenStorageInterface $tokenStorage, /*AccessDecisionManagerInterface*/ $accessDecisionManager, /*bool*/ $alwaysAuthenticate = false, /*bool*/ $exceptionOnNoToken = true)
    {
        if ($accessDecisionManager instanceof AuthenticationManagerInterface) {
            trigger_deprecation('symfony/security-core', '5.4', 'The $authenticationManager argument of "%s" is deprecated.', __METHOD__);

            $this->authenticationManager = $accessDecisionManager;
            $accessDecisionManager = $alwaysAuthenticate;
            $alwaysAuthenticate = $exceptionOnNoToken;
            $exceptionOnNoToken = \func_num_args() > 4 ? func_get_arg(4) : true;
        }

        if (false !== $alwaysAuthenticate) {
            trigger_deprecation('symfony/security-core', '5.4', 'Not setting the 4th argument of "%s" to "false" is deprecated.', __METHOD__);
        }
        if (false !== $exceptionOnNoToken) {
            trigger_deprecation('symfony/security-core', '5.4', 'Not setting the 5th argument of "%s" to "false" is deprecated.', __METHOD__);
        }

        if (!$accessDecisionManager instanceof AccessDecisionManagerInterface) {
            throw new \TypeError(sprintf('Argument 2 of "%s" must be instance of "%s", "%s" given.', __METHOD__, AccessDecisionManagerInterface::class, get_debug_type($accessDecisionManager)));
        }

        $this->tokenStorage = $tokenStorage;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->alwaysAuthenticate = $alwaysAuthenticate;
        $this->exceptionOnNoToken = $exceptionOnNoToken;
    }

    /**
     * {@inheritdoc}
     *
     * @throws AuthenticationCredentialsNotFoundException when the token storage has no authentication token and $exceptionOnNoToken is set to true
     */
    final public function isGranted($attribute, $subject = null): bool
    {
        return $this->getDecision($attribute, $subject)->isGranted();
    }

    public function getDecision($attribute, $subject = null): AccessDecision
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            if ($this->exceptionOnNoToken) {
                throw new AuthenticationCredentialsNotFoundException('The token storage contains no authentication token. One possible reason may be that there is no firewall configured for this URL.');
            }

            $token = new NullToken();
        } elseif ($this->authenticationManager && ($this->alwaysAuthenticate || !$token->isAuthenticated())) {
            $this->tokenStorage->setToken($token = $this->authenticationManager->authenticate($token));
        }

        if (!method_exists($this->accessDecisionManager, 'getDecision')) {
            trigger_deprecation('symfony/security-core', 5.4, 'Not implementing "%s::getDecision()" method is deprecated, and would be required in 6.0.', \get_class($this->accessDecisionManager));

            return $this->accessDecisionManager->decide($token, [$attribute], $subject) ? AccessDecision::createGranted() : AccessDecision::createDenied();
        }

        return $this->accessDecisionManager->getDecision($token, [$attribute], $subject);
    }
}
