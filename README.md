User Provider for Silex
=======================

[![Build Status](https://travis-ci.org/chrootLogin/silex-userprovider.svg?branch=nextgen)](https://travis-ci.org/chrootLogin/silex-userprovider)
[![Total Downloads](https://poser.pugx.org/rootlogin/silex-userprovider/downloads.svg)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Stable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/stable)](https://packagist.org/packages/rootlogin/silex-userprovider)
[![Latest Unstable Version](https://poser.pugx.org/rootlogin/silex-userprovider/v/unstable.svg)](https://packagist.org/packages/rootLogin/silex-userprovider)

A simple, extensible, database-backed user provider for the Silex [security service](http://silex.sensiolabs.org/doc/providers/security.html).

The User Provider is an easy way to set up user accounts (authentication, authorization, and user administration) in the Silex PHP micro-framework. It provides drop-in services for Silex that implement the missing user management pieces for the Security component. It includes a basic User model, a database-backed user manager, controllers and views for user administration, and various supporting features.

Demo
----
* [Online demo](http://demoapp.rootlogin.ch/)
* [Demo code](https://github.com/chrootLogin/silex-demoapp)

Installation
------------

Install with composer. This command will automatically install the latest stable version:

```
$ composer require rootlogin/silex-userprovider
```

Documentation
-------------

You can find the documentation at [ReadTheDocs](http://silex-userprovider.readthedocs.org/en/nextgen/).