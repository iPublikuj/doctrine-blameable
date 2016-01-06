# Quickstart

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

## Usage

If you map the blame onto a string field, this extension will try to assign the user name. If you map the blame onto a association field, this extension will try to assign the user object to it.

### Annotation syntax

**@IPub\Mapping\Annotation\Blameable** used in entity property, tells that this column is blameable.

#### Available options

* **on**: main and required option which define event when a blame should be invoked. Allowed values are: **create**, **update**, **change** and **delete**.
* **field**: is used only for event **change** and specifies tracked property. When the tracked property is changed, blame is invoked.
* **value**: is used only for event change and with tracked field. When the value of tracked field is same as defined, blame is invoked.
* **association**: is like in doctrine column definition. It support two sub-attributes: **column** which define column name in database and **referencedColumn** referenced column name in database

### Getting user data

This extension needs some information about user - username, id or full entity, it depend on type of association. By default is set string association, so user value have to be string or integer.
To obtain user information a special [user service](https://github.com/iPublikuj/doctrine-blameable/blob/master/src/IPub/DoctrineBlameable/Security/UserCallable.php) is used. This service grab usual Nette\Security\User identity and provide it to the listener.

If you want to use your custom callable, just define it in the configuration:

```neon
doctrineBlameable:
    userCallable: Namespace\To\Your\UserCallable::class
```

If don't want to use user callable object, you can directly send user details to extension listener:

```php
$blameableListener->setUser($yourUserDetails);
```

But remember, if you are using string field association, provided value have to be string or have to implement ```__toString()``` method, and in case you are using reference association, value have to be instance of association entity.

### Example entity with string association

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $title;

    /**
     * @ORM\Column(name="body", type="string")
     */
    private $body;

    /**
     * @var string $createdBy
     *
     * @IPub\Blameable(on="create")
     * @ORM\Column(type="string")
     */
    private $createdBy;

    /**
     * @var string $updatedBy
     *
     * @IPub\Blameable(on="update")
     * @ORM\Column(type="string")
     */
    private $updatedBy;

    /**
     * @var string $contentChangedBy
     *
     * @ORM\Column(name="content_changed_by", type="string", nullable=true)
     * @IPub\Blameable(on="change", field={"title", "body"})
     */
    private $contentChangedBy;

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    public function getContentChangedBy()
    {
        return $this->contentChangedBy;
    }
}
```

### Example entity with reference association

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $title;

    /**
     * @ORM\Column(name="body", type="string")
     */
    private $body;

    /**
     * @var Namespace\To\Your\UserEntity $createdBy
     *
     * @IPub\Blameable(on="create")
     * @ORM\ManyToOne(targetEntity="Namespace\To\Your\UserEntity")
     * @ORM\JoinColumn(name="created_by", referencedColumnName="id")
     */
    private $createdBy;

    /**
     * @var Namespace\To\Your\UserEntity $updatedBy
     *
     * @IPub\Blameable(on="update")
     * @ORM\ManyToOne(targetEntity="Namespace\To\Your\UserEntity")
     * @ORM\JoinColumn(name="updated_by", referencedColumnName="id")
     */
    private $updatedBy;

    /**
     * @var Namespace\To\Your\UserEntity $contentChangedBy
     *
     * @IPub\Blameable(on="change", fields={"title", "body"})
     * @ORM\ManyToOne(targetEntity="Namespace\To\Your\UserEntity")
     * @ORM\JoinColumn(name="content_changed_by", referencedColumnName="id")
     */
    private $contentChangedBy;

    public function getId()
    {
        return $this->id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    public function getContentChangedBy()
    {
        return $this->contentChangedBy;
    }
}
```

### Automatic associations

Columns doesn't have to be specified with doctrine ORM definition, this extension can map them automatically, all depends on how you configure it.

#### Automated with string field

This type is by default. Listener will map field as doctrine usually do:

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @var string $createdBy
     *
     * @IPub\Blameable(on="create")
     */
    private $createdBy;
}
```

Column ```$createdBy``` will be mapped as string field with column name ```created_by```

#### Automated with entity association

At first, you have to provide name of your user entity to extension configuration

```neon
doctrineBlameable:
    userEntity: 'Namespace\To\Your\UserEntity'
```

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @var Namespace\To\Your\UserEntity $createdBy
     *
     * @IPub\Blameable(on="create")
     */
    private $createdBy;
}
```

Column ```$createdBy``` will be mapped as association field to entity ```Namespace\To\Your\UserEntity``` and with column name ```created_by_id``` and with referenced column name ```id```

If you need to change column name in database or reference column name, you can use attributes:

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @var Namespace\To\Your\UserEntity $createdBy
     *
     * @IPub\Blameable(on="create", association={"column":"created_by_user_id", "referencedColumn":"user_id"})
     */
    private $createdBy;
}
```

### Using dependency of property changes

One entity can relay on other entity, and blame could be triggered after external change:

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Type
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $title;

    /**
     * @ORM\OneToMany(targetEntity="Article", mappedBy="type")
     */
    private $articles;

    // ...
}
```

And we want to monitor when the article is published - The type is changed.

```php
<?php
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
use IPub\Mapping\Annotation as IPub;

/**
 * @ORM\Entity
 */
class Article
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $title;

    /**
     * @ORM\ManyToOne(targetEntity="Type", inversedBy="articles")
     */
    private $type;

    /**
     * @var string $publishedBy
     *
     * @ORM\Column(type="string", nullable=true)
     * @IPub\Blameable(on="change", field="type.title", value="Published")
     */
    private $publishedBy;

    // ...
```

When the article type is changed to **Published** blame event for ```$publishedBy``` will be invoked.

### Using traits

**Note:** this feature is only available since php 5.4.0. And you are not required to use the Traits provided by extensions.

You can use extension traits for quick createdBy updatedBy property definitions. This traits are splitted into two, one for entity author and one for entity editor.

```php
<?php
namespace Blameable\Fixture;

use Doctrine\ORM\Mapping as ORM;
use IPub\DoctrineBlameable\Entities;

/**
 * @ORM\Entity
 */
class UsingTrait implements Entities\IEntityAuthor, Entities\IEntityEditor,
{
    /**
     * Hook blameable behavior for entity author
     * updates createdBy field
     */
    use Entities\TEntityAuthor;

    /**
     * Hook blameable behavior for entity editor
     * updates modifiedBy field
     */
    use Entities\TEntityEditor;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(length=128)
     */
    private $title;
}
```
