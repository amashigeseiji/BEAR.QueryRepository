<?php
/**
 * This file is part of the BEAR.QueryRepository package.
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace BEAR\QueryRepository;

use BEAR\RepositoryModule\Annotation\Cacheable;
use BEAR\RepositoryModule\Annotation\Storage;
use BEAR\Resource\AbstractUri;
use BEAR\Resource\ResourceObject;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\Cache;

class QueryRepository implements QueryRepositoryInterface
{
    const ETAG_BY_URI = 'etag-by-uri';

    /**
     * @var Cache
     */
    private $kvs;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var Expiry
     */
    private $expiry;

    /**
     * @var EtagSetterInterface
     */
    private $setEtag;

    /**
     * @Storage("kvs")
     */
    public function __construct(
        EtagSetterInterface $setEtag,
        Cache $kvs,
        Reader $reader,
        Expiry $expiry
    ) {
        $this->setEtag = $setEtag;
        $this->reader = $reader;
        $this->kvs = $kvs;
        $this->expiry = $expiry;
    }

    /**
     * {@inheritdoc}
     */
    public function put(ResourceObject $ro)
    {
        ($this->setEtag)($ro);
        if (isset($ro->headers['ETag'])) {
            $this->updateEtagDatabase($ro);
        }
        /* @var $cacheable Cacheable */
        $cacheable = $this->getCacheable($ro);
        $lifeTime = $this->getExpiryTime($cacheable);
        if ($cacheable instanceof Cacheable && $cacheable->type === 'view') {
            if (! $ro->view) {
                // render
                $ro->view = $ro->toString();
            }

            return $this->kvs->save((string) $ro->uri, [$ro->code, $ro->headers, $ro->body, $ro->view], $lifeTime);
        }
        // "value" cache type
        return $this->kvs->save((string) $ro->uri, [$ro->code, $ro->headers, $ro->body, null], $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function get(AbstractUri $uri)
    {
        $data = $this->kvs->fetch((string) $uri);
        if ($data === false) {
            return false;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(AbstractUri $uri)
    {
        $this->deleteEtagDatabase($uri);

        return $this->kvs->delete((string) $uri);
    }

    /**
     * Delete etag in etag repository
     *
     * @param AbstractUri $uri
     */
    public function deleteEtagDatabase(AbstractUri $uri)
    {
        $etagId = self::ETAG_BY_URI . (string) $uri; // invalidate etag
        $oldEtagKey = $this->kvs->fetch($etagId);

        $this->kvs->delete($oldEtagKey);
    }

    /**
     * @return Cacheable|null
     */
    private function getCacheable(ResourceObject $ro)
    {
        if (property_exists($ro, 'classAnnotations')) {
            $annotations = unserialize($ro->classAnnotations);

            return $annotations[Cacheable::class];
        }
        /** @var Cacheable|null $cache */
        $cache = $this->reader->getClassAnnotation(new \ReflectionClass($ro), Cacheable::class);

        return $cache;
    }

    /**
     * Update etag in etag repository
     *
     * @param ResourceObject $ro
     */
    private function updateEtagDatabase(ResourceObject $ro)
    {
        $etag = $ro->headers['ETag'];
        $uri = (string) $ro->uri;
        $etagUri = self::ETAG_BY_URI . $uri;
        $oldEtag = $this->kvs->fetch($etagUri);
        if ($oldEtag) {
            $this->kvs->delete($oldEtag);
        }
        $etagId = HttpCache::ETAG_KEY . $etag;
        $this->kvs->save($etagId, $uri);     // save etag
        $this->kvs->save($etagUri, $etagId); // save uri  mapping etag
    }

    /**
     * @param Cacheable $cacheable
     *
     * @return int
     */
    private function getExpiryTime(Cacheable $cacheable = null)
    {
        if ($cacheable === null) {
            return 0;
        }

        return $cacheable->expirySecond ? $cacheable->expirySecond : $this->expiry[$cacheable->expiry];
    }
}
