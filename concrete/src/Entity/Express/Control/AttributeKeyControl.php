<?php
namespace Concrete\Core\Entity\Express\Control;

use Concrete\Core\Entity\Attribute\Key\Key;
use Concrete\Core\Entity\Express\Entity;
use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Express\Form\Control\Form\AttributeKeyControlFormRenderer;
use Concrete\Core\Express\Form\Control\View\AttributeKeyControlViewRenderer;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="ExpressFormFieldSetAttributeKeyControls")
 */
class AttributeKeyControl extends Control
{
    /**
     * @ORM\ManyToOne(targetEntity="\Concrete\Core\Entity\Attribute\Key\Key")
     * @ORM\JoinColumn(name="akID", referencedColumnName="akID")
     */
    protected $attribute_key;

    /**
     * @return Key
     */
    public function getAttributeKey()
    {
        return $this->attribute_key;
    }

    /**
     * @param mixed $attribute_key
     */
    public function setAttributeKey($attribute_key)
    {
        $this->attribute_key = $attribute_key;
    }

    public function getFormControlRenderer()
    {
        return new AttributeKeyControlFormRenderer();
    }

    public function getViewControlRenderer()
    {
        return new AttributeKeyControlViewRenderer();
    }

    public function getControlLabel()
    {
        return $this->getAttributeKey()->getAttributeKeyDisplayName();
    }

    public function getType()
    {
        return 'attribute_key';
    }

    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();
        $data['attributeType'] = $this->getAttributeKey()->getAttributeTypeHandle();
        return $data;
    }

    public function getExporter()
    {
        return new \Concrete\Core\Export\Item\Express\Control\AttributeKeyControl();
    }

}
