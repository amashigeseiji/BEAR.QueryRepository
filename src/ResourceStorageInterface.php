<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\AbstractUri;
use BEAR\Resource\ResourceObject;

interface ResourceStorageInterface
{
    /**
     * Is ETag registered ?
     */
    public function hasEtag(string $etag) : bool;

    /**
     * Update or save new Etag
     *
     * @return void
     */
    public function updateEtag(ResourceObject $ro, int $lifeTime);

    /**
     * Delete Etag
     *
     * @return void
     */
    public function deleteEtag(AbstractUri $uri);

    /**
     * Get resource cache
     *
     * return [$uri, $code, $headers, $body, $view]] array.
     *
     * @return array{0:string, 1: int, 2:array<string, string>, 3: mixed, 4: string}|false
     */
    public function get(AbstractUri $uri);

    /**
     * Save resource cache with value
     *
     * @return bool
     */
    public function saveValue(ResourceObject $ro, int $ttl);

    /**
     * Save resource cache with view
     *
     * @return bool
     */
    public function saveView(ResourceObject $ro, int $ttl);

    /**
     * Delete resource cache
     */
    public function delete(AbstractUri $uri) : bool;
}
