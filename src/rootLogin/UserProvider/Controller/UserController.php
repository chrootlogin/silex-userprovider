<?php

/**
 * Silex User Provider
 *
 *  Copyright 2016 by Simon Erhardt <hello@rootlogin.ch>
 *
 * This file is part of the silex user provider.
 *
 * The silex user provider is free software: you can redistribute
 * it and/or modify it under the terms of the Lesser GNU General Public
 * License version 3 as published by the Free Software Foundation.
 *
 * The silex user provider is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the Lesser GNU General Public
 * License along with the silex user provider.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license LGPL-3.0 <http://spdx.org/licenses/LGPL-3.0>
 */

namespace rootLogin\UserProvider\Controller;

use rootLogin\UserProvider\Entity\User;
use rootLogin\UserProvider\Form\Model\PasswordChange;
use rootLogin\UserProvider\Form\Model\PasswordForgotten;
use rootLogin\UserProvider\Form\Model\PasswordReset;
use rootLogin\UserProvider\Interfaces\UserManagerInterface;
use Silex\Application;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use InvalidArgumentException;
use JasonGrimes\Paginator;
use Symfony\Component\Translation\Translator;

/**
 * Controller with actions for handling form-based authentication and user management.
 *
 * @package rootLogin\UserProvider\Controller
 */
class UserController
{
    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var UserManagerInterface
     */
    protected $userManager;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Template settings
     *
     * @var array
     */
    protected $templates = [
        'layout' => '@user/layout.html.twig',
        'fragment-layout' => '@user/fragment-layout.html.twig',
        'register' => '@user/register.html.twig',
        'register-confirmation-sent' => '@user/register-confirmation-sent.html.twig',
        'login' => '@user/login.html.twig',
        'login-confirmation-needed' => '@user/login-confirmation-needed.html.twig',
        'forgot-password' => '@user/forgot-password.html.twig',
        'reset-password' => '@user/reset-password.html.twig',
        'view' => '@user/view.html.twig',
        'edit' => '@user/edit.html.twig',
        'change-password' => '@user/change-password.html.twig',
        'list' => '@user/list.html.twig',
    ];

    /**
     * Form settings
     *
     * @var array
     */
    protected $forms = [
        'register' => 'rup_register',
        'edit' => 'rup_edit',
        'change_password' => 'rup_change_password',
        'forgot_password' => 'rup_forgot_password',
        'reset_password' => 'rup_reset_password'
    ];

    protected $isUsernameRequired = false;
    protected $isEmailConfirmationRequired = false;
    protected $isPasswordResetEnabled = true;

    // Custom fields to support in the editAction().
    /** @deprecated not used anymore */
    protected $editCustomFields = array();

    /**
     * Constructor.
     *
     * @param UserManagerInterface $userManager
     */
    public function __construct(UserManagerInterface $userManager, FormFactoryInterface $formFactory, Translator $translator)
    {
        $this->userManager = $userManager;
        $this->formFactory = $formFactory;
        $this->translator = $translator;
    }

    /**
     * Login action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function loginAction(Application $app, Request $request)
    {
        $authException = $app['user.last_auth_exception']($request);

        if ($authException instanceof DisabledException) {
            // This exception is thrown if (!$user->isEnabled())
            // Warning: Be careful not to disclose any user information besides the email address at this point.
            // The Security system throws this exception before actually checking if the password was valid.
            $user = $this->userManager->refreshUser($authException->getUser());

            return $app['twig']->render($this->getTemplate('login-confirmation-needed'), [
                'layout_template' => $this->getTemplate('layout'),
                'email' => $user->getEmail(),
                'fromAddress' => $app['user.mailer']->getFromAddress(),
                'resendUrl' => $app['url_generator']->generate('user.resend-confirmation'),
            ]);
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('login'), [
            'layout_template' => $template,
            'error' => $authException ? $this->trans($authException->getMessageKey()) : null,
            'last_username' => $app['session']->get('_security.last_username'),
            'allowRememberMe' => isset($app['security.remember_me.response_listener']),
            'allowPasswordReset' => $this->isPasswordResetEnabled(),
        ]);
    }

    /**
     * Register action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function registerAction(Application $app, Request $request)
    {
        $registerForm = $this->formFactory->createBuilder($this->forms['register'], $this->userManager->getEmptyUser());
        $registerForm = $registerForm->getForm();

        $registerForm->handleRequest($request);

        if ($registerForm->isValid()) {
            /** @var User $user */
            $user = $registerForm->getData();

            if ($this->isEmailConfirmationRequired) {
                $user->setEnabled(false);
                $user->setConfirmationToken($app['user.tokenGenerator']->generateToken());
            }
            $this->userManager->setUserPassword($user, $user->getPlainPassword());
            $this->userManager->save($user);

            if ($this->isEmailConfirmationRequired) {
                // Send email confirmation.
                $app['user.mailer']->sendConfirmationMessage($user);

                // Render the "go check your email" page.
                return $app['twig']->render($this->getTemplate('register-confirmation-sent'), [
                    'layout_template' => $this->getTemplate('layout'),
                    'email' => $user->getEmail(),
                ]);
            } else {
                // Log the user in to the new account.
                $this->userManager->loginAsUser($user);

                $app['session']->getFlashBag()->set('alert', 'Account created.');

                // Redirect to user's new profile page.
                return $app->redirect($app['url_generator']->generate('user.view', ['id' => $user->getId()]));
            }
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('register'), [
            'layout_template' => $template,
            'registerForm' => $registerForm->createView()
        ]);
    }

    /**
     * View user action.
     *
     * @param Application $app
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException if no user is found with that ID.
     */
    public function viewAction(Application $app, Request $request, $id)
    {
        $user = $this->userManager->getUser($id);

        if (!$user) {
            throw new NotFoundHttpException($this->trans('No user was found with that ID.'));
        }

        if (!$user->isEnabled() && !$app['security']->isGranted('ROLE_ADMIN')) {
            throw new NotFoundHttpException($this->trans('That user is disabled (pending email confirmation).'));
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('view'), [
            'layout_template' => $template,
            'user' => $user,
            'imageUrl' => $this->getGravatarUrl($user->getEmail()),
        ]);

    }

    /**
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function viewSelfAction(Application $app, Request $request)
    {
        $id = $app['user']->getId();

        return $this->viewAction($app, $request, $id);
    }

    /**
     * Edit user action.
     *
     * @param Application $app
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException if no user is found with that ID.
     */
    public function editAction(Application $app, Request $request, $id)
    {
        $user = $this->userManager->getUser($id);
        if(!$user) {
            throw new NotFoundHttpException($this->trans('No user was found with that ID.'));
        }

        $editForm = $this->formFactory->createBuilder($this->forms['edit'], $user);
        $editForm = $editForm->getForm();

        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            /** @var User $user */
            $user = $editForm->getData();

            $this->userManager->save($user);

            $msg = $this->trans('Saved account information.');
            $app['session']->getFlashBag()->set('alert', $msg);
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('edit'), [
            'layout_template' => $template,
            'editForm' => $editForm->createView(),
            'user' => $user
        ]);
    }

    /**
     * Self-service of the user
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function editSelfAction(Application $app, Request $request)
    {
        $id = $app['user']->getId();

        return $this->editAction($app, $request, $id);
    }

    /**
     * Change password action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function changePasswordAction(Application $app, Request $request)
    {
        /** @var User $user */
        $user = $this->userManager->getCurrentUser();
        if(!$user) {
            throw new AccessDeniedException($this->trans('You need to be logged in.'));
        }

        $changePasswordForm = $this->formFactory->createBuilder($this->forms['change_password'], new PasswordChange());
        $changePasswordForm = $changePasswordForm->getForm();

        $changePasswordForm->handleRequest($request);

        if ($changePasswordForm->isValid()) {
            /** @var PasswordChange $passwordChange */
            $passwordChange = $changePasswordForm->getData();

            $this->userManager->setUserPassword($user, $passwordChange->getNewPassword());
            $this->userManager->save($user);

            $msg = $this->trans('Changed password.');
            $app['session']->getFlashBag()->set('alert', $msg);
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('change-password'), [
            'layout_template' => $template,
            'changePasswordForm' => $changePasswordForm->createView()
        ]);
    }

    /**
     * Action to handle email confirmation links.
     *
     * @param Application $app
     * @param Request $request
     * @param string $token
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function confirmEmailAction(Application $app, Request $request, $token)
    {
        $user = $this->userManager->findOneBy(['confirmationToken' => $token]);
        if (!$user) {
            $app['session']->getFlashBag()->set('alert', $this->trans('Sorry, your email confirmation link has expired.'));

            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        $user->setConfirmationToken(null);
        $user->setEnabled(true);
        $this->userManager->save($user);

        $app['session']->getFlashBag()->set('alert', $this->trans('Thank you! Your account has been activated.'));

        return $app->redirect($app['url_generator']->generate('user.view', ['id' => $user->getId()]));
    }

    /**
     * Action to resend an email confirmation message.
     *
     * @param Application $app
     * @param Request $request
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function resendConfirmationAction(Application $app, Request $request)
    {
        $email = $request->request->get('email');
        $user = $this->userManager->findOneBy(['email' => $email]);
        if (!$user) {
            throw new NotFoundHttpException($this->trans('No user account was found with that email address.'));
        }

        if (!$user->getConfirmationToken()) {
            $user->setConfirmationToken($app['user.tokenGenerator']->generateToken());
            $this->userManager->save($user);
        }

        $app['user.mailer']->sendConfirmationMessage($user);

        // Render the "go check your email" page.
        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('register-confirmation-sent'), [
            'layout_template' => $template,
            'email' => $user->getEmail(),
        ]);
    }

    /**
     * Forgot Password action.
     *
     * @param Application $app
     * @param Request $request
     * @return mixed
     */
    public function forgotPasswordAction(Application $app, Request $request)
    {
        if (!$this->isPasswordResetEnabled()) {
            throw new NotFoundHttpException($this->trans('Password resetting is not enabled.'));
        }

        $forgotPasswordForm = $this->formFactory->createBuilder($this->forms['forgot_password'], new PasswordForgotten());
        $forgotPasswordForm = $forgotPasswordForm->getForm();

        $forgotPasswordForm->handleRequest($request);

        if ($forgotPasswordForm->isValid()) {
            /** @var PasswordForgotten $passwordForgotten */
            $passwordForgotten = $forgotPasswordForm->getData();

            $user = $this->userManager->findOneBy(['email' => $passwordForgotten->getEmail()]);
            if ($user) {
                // Initialize and send the password reset request.
                $user->setTimePasswordResetRequested(new \DateTime());
                if (!$user->getConfirmationToken()) {
                    $user->setConfirmationToken($app['user.tokenGenerator']->generateToken());
                }
                $this->userManager->save($user);

                $app['user.mailer']->sendResetMessage($user);
                $app['session']->getFlashBag()->set('alert', $this->trans('Instructions for resetting your password have been emailed to you.'));

                return $app->redirect($app['url_generator']->generate('user.login'));
            }

            // This should not happen, because the form gets validated by the EMailExists validator.
            $msg = 'Internal error: User was not found.';
            $app['session']->getFlashBag()->set('alert', $msg);
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('forgot-password'), [
            'layout_template' => $template,
            'forgotPasswordForm' => $forgotPasswordForm->createView(),
            'fromAddress' => $app['user.mailer']->getFromAddress()
        ]);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param string $token
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function resetPasswordAction(Application $app, Request $request, $token)
    {
        if (!$this->isPasswordResetEnabled()) {
            throw new NotFoundHttpException($this->trans('Password resetting is not enabled.'));
        }

        $tokenExpired = false;

        $user = $this->userManager->findOneBy(['confirmationToken' => $token]);
        if ($user === null || $user->isPasswordResetRequestExpired($app['user.options']['passwordReset']['tokenTTL'])) {
            $tokenExpired = true;
        }

        if ($tokenExpired) {
            $app['session']->getFlashBag()->set('alert', $this->trans('Sorry, your password reset link has expired.'));
            return $app->redirect($app['url_generator']->generate('user.login'));
        }

        $resetPasswordForm = $this->formFactory->createBuilder($this->forms['reset_password'], new PasswordReset());
        $resetPasswordForm = $resetPasswordForm->getForm();

        $resetPasswordForm->handleRequest($request);
        if ($resetPasswordForm->isValid()) {
            /** @var PasswordReset $passwordReset */
            $passwordReset = $resetPasswordForm->getData();

            $this->userManager->setUserPassword($user, $passwordReset->getNewPassword());
            $user->setConfirmationToken(null);
            $user->setEnabled(true);
            $this->userManager->save($user);

            $this->userManager->loginAsUser($user);

            $app['session']->getFlashBag()->set('alert', $this->trans('Your password has been reset and you are now signed in.'));

            return $app->redirect($app['url_generator']->generate('user'));
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('reset-password'), [
            'layout_template' => $template,
            'resetPasswordForm' => $resetPasswordForm->createView(),
            'user' => $user
        ]);
    }

    public function listAction(Application $app, Request $request)
    {
        $order_by = $request->get('order_by') ?: 'name';
        $order_dir = $request->get('order_dir') == 'DESC' ? 'DESC' : 'ASC';
        $limit = (int)($request->get('limit') ?: 50);
        $page = (int)($request->get('page') ?: 1);
        $offset = ($page - 1) * $limit;

        $criteria = array();
        if (!$app['security']->isGranted('ROLE_ADMIN')) {
            $criteria['isEnabled'] = true;
        }

        $users = $this->userManager->findBy($criteria, array($order_by => $order_dir), $limit, $offset);

        $numResults = $this->userManager->findCount($criteria);

        $paginator = new Paginator($numResults, $limit, $page,
            $app['url_generator']->generate('user.list') . '?page=(:num)&limit=' . $limit . '&order_by=' . $order_by . '&order_dir=' . $order_dir
        );

        foreach ($users as $user) {
            $user->imageUrl = $this->getGravatarUrl($user->getEmail(), 40);
        }

        // if ?_fragment is set, then show the fragment template
        $template = $request->get('_fragment') !== null ? $this->getTemplate('fragment-layout') : $this->getTemplate('layout');
        return $app['twig']->render($this->getTemplate('list'), [
            'layout_template' => $template,
            'users' => $users,
            'paginator' => $paginator,

            // The following variables are no longer used in the default template,
            // but are retained for backward compatibility.
            'numResults' => $paginator->getTotalItems(),
            'nextUrl' => $paginator->getNextUrl(),
            'prevUrl' => $paginator->getPrevUrl(),
            'firstResult' => $paginator->getCurrentPageFirstItem(),
            'lastResult' => $paginator->getCurrentPageLastItem(),
        ]);
    }

    /**
     * Specify custom fields to support in the editAction().
     *
     * @deprecated not in use anymore
     * @param array $editCustomFields
     */
    public function setEditCustomFields(array $editCustomFields)
    {
        $this->editCustomFields = $editCustomFields;
    }


    /**
     * @param boolean $passwordResetEnabled
     */
    public function setPasswordResetEnabled($passwordResetEnabled)
    {
        $this->isPasswordResetEnabled = (bool) $passwordResetEnabled;
    }

    /**
     * @return boolean
     */
    public function isPasswordResetEnabled()
    {
        return $this->isPasswordResetEnabled;
    }

    /**
     * @param bool $isUsernameRequired
     */
    public function setUsernameRequired($isUsernameRequired)
    {
        $this->isUsernameRequired = (bool) $isUsernameRequired;
    }

    public function setEmailConfirmationRequired($isRequired)
    {
        $this->isEmailConfirmationRequired = (bool) $isRequired;
    }

    /**
     * @param string $key
     * @param string $template
     */
    public function setTemplate($key, $template)
    {
        $this->templates[$key] = $template;
    }

    /**
     * @param array $templates
     */
    public function setTemplates(array $templates)
    {
        foreach ($templates as $key => $val) {
            $this->setTemplate($key, $val);
        }
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getTemplate($key)
    {
        return $this->templates[$key];
    }

    /**
     * @param string $key
     * @param string $form
     */
    public function setForm($key, $form)
    {
        $this->forms[$key] = $form;
    }

    /**
     * @param array $forms
     */
    public function setForms(array $forms)
    {
        foreach ($forms as $key => $val) {
            $this->setForm($key, $val);
        }
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getForm($key)
    {
        return $this->forms[$key];
    }

    /**
     * Shortcut for translating a string
     *
     * @param $message
     * @param array $parameters
     * @return string
     */
    protected function trans($message, array $parameters = [])
    {
        return $this->translator->trans($message, $parameters, 'messages');
    }

    /**
     * @param string $email
     * @param int $size
     * @return string
     */
    protected function getGravatarUrl($email, $size = 80)
    {
        // See https://en.gravatar.com/site/implement/images/ for available options.
        return '//www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=' . $size . '&d=identicon';
    }
}
