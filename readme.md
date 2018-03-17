# Doctrine Blameable

[![Build Status](https://img.shields.io/travis/iPublikuj/doctrine-blameable.svg?style=flat-square)](https://travis-ci.org/iPublikuj/doctrine-blameable)
[![Scrutinizer Code Coverage](https://img.shields.io/scrutinizer/coverage/g/iPublikuj/doctrine-blameable.svg?style=flat-square)](https://scrutinizer-ci.com/g/iPublikuj/doctrine-blameable/?branch=master)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/iPublikuj/doctrine-blameable.svg?style=flat-square)](https://scrutinizer-ci.com/g/iPublikuj/doctrine-blameable/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/ipub/doctrine-blameable.svg?style=flat-square)](https://packagist.org/packages/ipub/doctrine-blameable)
[![Composer Downloads](https://img.shields.io/packagist/dt/ipub/doctrine-blameable.svg?style=flat-square)](https://packagist.org/packages/ipub/doctrine-blameable)
[![License](https://img.shields.io/packagist/l/ipub/doctrine-blameable.svg?style=flat-square)](https://packagist.org/packages/ipub/doctrine-blameable)

Blameable behavior will automate the update of username or user reference fields on your Entities in [Nette Framework](http://nette.org/) and [Doctrine 2](http://www.doctrine-project.org/)

## Installation

The best way to install ipub/doctrine-blameable is using [Composer](http://getcomposer.org/):

```sh
$ composer require ipub/doctrine-blameable
```

After that you have to register extension in config.neon.

```neon
extensions:
	doctrineBlameable: IPub\DoctrineBlameable\DI\DoctrineBlameableExtension
```

## Documentation

Learn how to register and work with blameable behavior in [documentation](https://github.com/iPublikuj/doctrine-blameable/blob/master/docs/en/index.md).

***
Homepage [https://www.ipublikuj.eu](https://www.ipublikuj.eu) and repository [http://github.com/iPublikuj/doctrine-blameable](http://github.com/iPublikuj/doctrine-blameable).
