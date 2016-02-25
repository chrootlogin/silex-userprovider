User Provider for Silex
=======================

[![Build Status](https://travis-ci.org/chrootLogin/silex-userprovider.svg?branch=nextgen)](https://travis-ci.org/chrootLogin/silex-userprovider)
[![Total Downloads](https://poser.pugx.org/rootlogin/silex-userprovider/downloads.svg)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Stable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/stable)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Unstable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/unstable.svg)](https://packagist.org/packages/rootLogin/silex-userprovider)

A simple, extensible, database-backed user provider for the Silex [security service](http://silex.sensiolabs.org/doc/providers/security.html).

The User Provider is an easy way to set up user accounts (authentication, authorization, and user administration) in the Silex PHP micro-framework. It provides drop-in services for Silex that implement the missing user management pieces for the Security component. It includes a basic User model, a database-backed user manager, controllers and views for user administration, and various supporting features.

Dependencies
------------

* PHP ~5.4
* Silex ~1.0
* Doctrine DBAL ~2.4
* Twig ~1.2
* Symfony Security ~2.3
* Symfony Forms ~2.7
* Symfony Security CSRF ~2.7
* Symfony Validator ~2.7
* Symfony TwigBridge ~2.3
* Symfony Translation ~2.7
* Jasongrimes Paginator ~1.0
* Swiftmailer ~5.3

__Console Plugins__

 * Symfony Console ~2.7

__Doctrine ORM mode__

 * Dflydev Doctrine ORM Service Provider ~1.0



Demo
----

* [Online demo](http://demoapp.rootlogin.ch/)
* [Demo code](https://github.com/chrootLogin/silex-demoapp)