<?php

namespace App\Models;

use Exception;
use Couchbase\Scope;
use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\Collection;
use Couchbase\ClusterOptions;
use Couchbase\MutateInsertSpec;
use Couchbase\MutateRemoveSpec;
use Illuminate\Http\JsonResponse;

class CouchbaseModel
{
    private const CONNECTION_STRING = "couchbases:URL";
    private const USERNAME = "username";
    private const PASSWORD = "password";
    private Bucket $bucket;
    protected Scope $scope;
    protected Collection $collection;
    protected string $database;

    public function __construct()
    {
        $options = new ClusterOptions();
        $options->credentials(self::USERNAME, self::PASSWORD);
        $cluster = new Cluster(self::CONNECTION_STRING, $options);
        $this->bucket = $cluster->bucket(env('CB_BUCKET'));
        $this->scope = $this->bucket->scope(env('CB_SCOPE'));
        $this->collection = $this->scope->collection(static::COLLECTION_NAME);
        $this->database = $this->bucket->name() . "." . $this->scope->name() . "." . $this->collection->name();
    }

    public function getAllCollections(): array
    {
        $collectionKey = static::COLLECTION_KEY;
        $key = property_exists(static::DTO_CLASS, 'primary_key') ? 'primary_key' : static::DOCUMENT_KEY;

        $collections = $this->scope->query(
            "SELECT * FROM $this->database WHERE meta().id = '$collectionKey'"
        )->rows()[0][static::COLLECTION_NAME];

        usort($collections, fn($a, $b) => strcmp($a[$key], $b[$key]));

        return $this->generateCollectionOfObjects($collections);
    }

    /**
     * @return static::DTO_CLASS|string
     */
    public function getCollection(string $documentKey)
    {
        $className = static::DTO_CLASS;
        $collectionKey = static::COLLECTION_KEY;
        $collection = $this->scope->query(
            "SELECT d.['$documentKey'] as doc FROM $this->database as d WHERE  meta().id = '$collectionKey'"
        )->rows();

        return count($collection[0]) ? new $className(...$collection[0]['doc']) : 'No such document exists.';
    }

    /**
     * @param static::DTO_CLASS $collection
     */
    public function setCollection($collection): JsonResponse
    {
        try {
            $this->collection->mutateIn(static::COLLECTION_KEY, [
                new MutateInsertSpec($collection->{static::DOCUMENT_KEY}, $collection)
            ]);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'path_exists')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Duplicate Key',
                ]);
            }
            return response()->json([
                'status' => false,
                'message' => 'Got lost on the way! Please retry',
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => 'Added Successfully'
        ]);
    }

    /**
     * @param static::DTO_CLASS $collection
     */
    public function updateCollection($collection): JsonResponse
    {
        $collectionKey = static::COLLECTION_KEY;
        $documentKey = $collection->{static::DOCUMENT_KEY};
        $updatedString = '';

        foreach ($collection as $key => $value) {
            $value = is_int($value) ? $value : (is_bool($value) ? ($value ? "true" : "false") : "'$value'");
            $updatedString .= ($updatedString ? ", " : "") . "d.$documentKey.`$key` = $value";
        }

        $collection = $this->scope->query(
            "UPDATE $this->database AS d SET $updatedString WHERE meta().id = '$collectionKey'"
        );

        return response()->json([
            'status' => true,
            'message' => 'Updated Successfully'
        ]);
    }

    public function deleteCollection(string $documentKey): JsonResponse
    {
        try {
            $this->collection->mutateIn(static::COLLECTION_KEY, [
                new MutateRemoveSpec($documentKey)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully'
        ]);
    }

    public function generatePrimaryKey(): int
    {
        $maxPrimaryKey = $this->scope->query(
            "SELECT ARRAY_MAX(OBJECT_VALUES(d)[*].primary_key) AS maxKey FROM $this->database AS d WHERE meta().id = '" . static::COLLECTION_KEY . "'"
        )->rows()[0]['maxKey'];

        return ++$maxPrimaryKey;
    }

    public function generateDocumentKey(): string
    {
        $maxDocumentKey = $this->scope->query(
            "SELECT ARRAY_MAX(OBJECT_NAMES(d)) AS maxKey FROM $this->database AS d WHERE meta().id = '" . static::COLLECTION_KEY . "'"
        )->rows()[0]['maxKey'];

        return ++$maxDocumentKey;
    }

    /**
     * @param array<array> $collections
     * @return array<static::DTO_CLASS>
     */
    private function generateCollectionOfObjects(array $collections): array
    {
        $objectCollection = [];
        $className = static::DTO_CLASS;
        foreach ($collections as $collection) {
            $objectCollection[] = new $className(...$collection);
        }

        return $objectCollection;
    }
}
