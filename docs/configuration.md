Config options
--------------

All of these options are _optional_.
The UserProvider can work without any configuration at all,
or you can customize one or more of the following options.
The default values are shown below.

```
$app['user.options'] = [

            // Specify custom view templates here.
            'templates' => [
                'layout' => '@user/layout.twig',
                'register' => '@user/register.twig',
                'register-confirmation-sent' => '@user/register-confirmation-sent.twig',
                'login' => '@user/login.twig',
                'login-confirmation-needed' => '@user/login-confirmation-needed.twig',
                'forgot-password' => '@user/forgot-password.twig',
                'reset-password' => '@user/reset-password.twig',
                'view' => '@user/view.twig',
                'edit' => '@user/edit.twig',
                'list' => '@user/list.twig',
            ],

            // Specify the forms
            'forms' => [
                'register' => 'rup_register',
                'edit' => 'rup_edit',
                'change_password' => 'rup_change_password',
                'forgot_password' => 'rup_forgot_password',
                'reset_password' => 'rup_reset_password'
            ],

            // Configure the user mailer for sending password reset and email confirmation messages.
            'mailer' => [
                'enabled' => true, // When false, email notifications are not sent (they're silently discarded).
                'fromEmail' => [
                    'address' => 'do-not-reply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
                    'name' => null,
                ],
            ],

            'emailConfirmation' => [
                'required' => false, // Whether to require email confirmation before enabling new accounts.
                'template' => '@user/email/confirm-email.twig',
            ],

            'passwordReset' => [
                'template' => '@user/email/reset-password.twig',
                'tokenTTL' => 86400, // How many seconds the reset token is valid for. Default: 1 day.
            ],

            // Set this to use a custom User class.
            'userClass' => 'rootLogin\UserProvider\Entity\User',

            // Whether to require that users have a username (default: false).
            // By default, users sign in with their email address instead.
            'isUsernameRequired' => false,

            // A list of custom fields to support in the edit controller. (dbal mode only)
            'editCustomFields' => [],

            // Override table names, if necessary. (dbal only)
            'userTableName' => 'users',
            'userCustomFieldsTableName' => 'user_custom_fields',

            // Override column names if necessary. (dbal mode only)
            'userColumns' => [
                'id' => 'id',
                'email' => 'email',
                'password' => 'password',
                'salt' => 'salt',
                'roles' => 'roles',
                'name' => 'name',
                'time_created' => 'time_created',
                'username' => 'username',
                'isEnabled' => 'isEnabled',
                'confirmationToken' => 'confirmationToken',
                'timePasswordResetRequested' => 'timePasswordResetRequested',
                //Custom Fields
                'user_id' => 'user_id',
                'attribute' => 'attribute',
                'value' => 'value',
            ]
        ];
```

Commandline
-----------

If you have enabled the symfony console, as with [saxulum-console](https://github.com/saxulum/saxulum-console) for example, the provider will add some commands to the console:

* `userprovider:create`: Create an user
* `userprovider:list`: List users
* `userprovider:delete`: Delete an user
* `userprovider:role:add`: Add a role to an user
* `userprovider:role:list`: List user's roles
* `userprovider:delete`: Remove a role from an user

Use Doctrine ORM instead of DBAL
--------------------------------

The provider uses the Doctrine Orm (Object-relational mapper) automatically, if the necessairy providers are found. (See Additional Dependencies)

ATTENTION! An auto migration is no possible.

### Additional dependencies

* A Doctrine Orm Provider, like [dflydev/dflydev-doctrine-orm-service-provider](https://github.com/dflydev/dflydev-doctrine-orm-service-provider).

### Usage

If the server provider finds another orm service provider it will automatically add itself.

### Force DBAL

If you use Doctrine ORM in your project and you want to force the DBAL handling for the UserProvider, register the provider like this:

```
// ...

$app->register(new UserProviderServiceProvider(true));

// ...
```