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
     * @ORM\String
     */
    private $body;

    /**
     * @var User $createdBy
     *
     * @IPub\Blameable(on="create")
     * @ORM\ManyToOne(targetEntity="Path\To\Entity\User")
     * @ORM\JoinColumn(name="created_by", referencedColumnName="id")
     */
    private $createdBy;

    /**
     * @var User $updatedBy
     *
     * @IPub\Blameable(on="update")
     * @ORM\ManyToOne(targetEntity="Path\To\Entity\User")
     * @ORM\JoinColumn(name="updated_by", referencedColumnName="id")
     */
    private $updatedBy;

    /**
     * @var User $contentChangedBy
     *
     * @IPub\Blameable(on="change", fields={"title", "body"})
     * @ORM\ManyToOne(targetEntity="Path\To\Entity\User")
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
