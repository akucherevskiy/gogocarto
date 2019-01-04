<?php

namespace Biopen\CoreBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

abstract class WebhookFormat
{
    const Raw = 'raw';
    const Mattermost = 'mattermost';
    const Slack = 'slack';
}

/** @MongoDB\Document */
class Webhook
{
    /**
     * @var int
     * @MongoDB\Id(strategy="INCREMENT") 
     */
    private $id;

    /** @MongoDB\Field(type="string") */
    public $format;

    /** @MongoDB\Field(type="string") */
    public $url;

    function __toString()
    {
        return $this->getUrl() ? $this->getUrl() : "";
    }

    /**
     * Get id
     *
     * @return int $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set format
     *
     * @param string $format
     * @return $this
     */
    public function setName($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Get format
     *
     * @return string $format
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get url
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }
}
