<?php

namespace LOCKSSOMatic\CrudBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use LOCKSSOMatic\CrudBundle\Entity\Content;
use LOCKSSOMatic\CrudBundle\Entity\ContentProperty;
use Monolog\Logger;
use SimpleXMLElement;
use Symfony\Component\Routing\Router;

/**
 * Build a piece of content.
 */
class ContentBuilder
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var AuIdGenerator
     */
    private $idGenerator;

    /**
     * Set the logger
     *
     * @param Logger $logger
     */
    public function setLogger(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Set the entity manager via a poorly named function.
     *
     * @param Registry $registry
     */
    public function setRegistry(Registry $registry) {
        $this->em = $registry->getManager();
    }

    /**
     * Set the page router.
     *
     * @param Router $router
     */
    public function setRouter(Router $router) {
        $this->router = $router;
    }

    /**
     * Set the AU Id generate.
     *
     * @param AuIdGenerator $idGenerator
     */
    public function setAuIdGenerator(AuIdGenerator $idGenerator) {
        $this->idGenerator = $idGenerator;
    }

    /**
     * Build a property for the content. If $this->em is set, the property
     * is persisted to the database.
     *
     * @param Content $content
     * @param string $key
     * @param string $value
     *
     * @return ContentProperty
     */
    public function buildProperty(Content $content, $key, $value) {
        $contentProperty = new ContentProperty();
        $contentProperty->setContent($content);
        $contentProperty->setPropertyKey($key);
        $contentProperty->setPropertyValue($value);
        if ($this->em !== null) {
            $this->em->persist($contentProperty);
        }

        return $contentProperty;
    }

    /**
     * Build a content item from some XML. Persists to the database if
     * $this->em is set.
     *
     * @param SimpleXMLElement $xml
     *
     * @return Content
     */
    public function fromSimpleXML(SimpleXMLElement $xml) {
        $content = new Content();
        $content->setSize((string) $xml->attributes()->size);
        $content->setChecksumType((string) $xml->attributes()->checksumType);
        $content->setChecksumValue((string) $xml->attributes()->checksumValue);
        $content->setUrl(trim((string) $xml));
        $content->setRecrawl(true);
        $content->setDepositDate();
        $this->buildProperty($content, 'journalTitle', (string) $xml->attributes('pkp', true)->journalTitle);
        $this->buildProperty($content, 'publisher', (string) $xml->attributes('pkp', true)->publisher);
        $content->setTitle($xml->attributes('pkp', true)->journalTitle);
        if ($this->em !== null) {
            $this->em->persist($content);
        }

        foreach ($xml->xpath('lom:property') as $node) {
            $this->buildProperty($content, (string) $node->attributes()->name, (string) $node->attributes()->value);
        }

        return $content;
    }

    /**
     * Build a content item from an array, probably from a CSV file. The $record
     * requires size, checksum type, checksum value, url. Title, is optional
     * and anything required by the relevant LOCKSS plugin is also required.
     *
     * @param array $record
     * @return Content
     */
    public function fromArray($record) {
        $content = new Content();
        $content->setSize($record['size']);
        $content->setChecksumType($record['checksum type']);
        $content->setChecksumValue($record['checksum value']);
        $content->setUrl($record['url']);
        $content->setRecrawl(true);
        if (array_key_exists('title', $record)) {
            $content->setTitle($record['title']);
        } else {
            $content->setTitle('Generated Title');
        }
        $content->setDepositDate();

        if ($this->em !== null) {
            $this->em->persist($content);
        }

        foreach (array_keys($record) as $key) {
            $this->buildProperty($content, $key, $record[$key]);
        }

        return $content;
    }
}
