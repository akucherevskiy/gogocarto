<?php

namespace Application\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Biopen\SaasBundle\Helper\SaasHelper;

class AppExtension extends AbstractExtension
{
    public function __construct($dm)
    {
        $this->dm = $dm;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('json_decode', array($this, 'jsonDecode')),            
        );
    }

    public function jsonDecode($value)
    {
        return json_decode($value);
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('is_root_project', array($this, 'isRootProject')),
            new TwigFunction('new_msgs_count', array($this, 'getNewMessagesCount')),
            new TwigFunction('errors_count', array($this, 'getErrorsCount')),
        );
    }

    public function isRootProject()
    {
        $sassHelper = new SaasHelper();
        return $sassHelper->isRootProject();
    }

    public function getNewMessagesCount()
    {
        return count($this->dm->getRepository('BiopenCoreBundle:GoGoLog')->findBy(['type' => 'update', 'hidden' => false])); 
    }

    public function getErrorsCount()
    {
        return count($this->dm->getRepository('BiopenCoreBundle:GoGoLog')->findBy(['level' => 'error', 'hidden' => false])); 
    }
}