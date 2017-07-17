<?php

namespace Corcel\Traits;

use Corcel\Model\Attachment;
use Corcel\Model\CustomLink;
use Corcel\Model\MenuItem;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use ReflectionClass;

/**
 * Trait HasMetaFields
 *
 * @package Corcel\Traits
 * @author Junior Grossi <juniorgro@gmail.com>
 */
trait HasMetaFields
{
    /**
     * @var array
     */
    private $relatedMetaClasses = [
        Attachment::class => Post::class,
        CustomLink::class => Post::class,
        MenuItem::class => Post::class,
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function meta()
    {
        return $this->hasMany(
            $this->getClassName(), $this->getFieldName()
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fields()
    {
        return $this->meta();
    }

    /**
     * @param Builder $query
     * @param string $meta
     * @param mixed $value
     * @return Builder
     */
    public function scopeHasMeta(Builder $query, $meta, $value = null)
    {
        if (!is_array($meta)) {
            $meta = [$meta => $value];
        }

        foreach($meta as $key => $value) {
            $query->whereHas('meta', function ($query) use ($key, $value) {
                if (is_string($key)) {
                    $query->where('meta_key', $key);

                    return is_null($value) ? $query : // 'foo' => null
                        $query->where('meta_value', $value); // 'foo' => 'bar'
                }

                return $query->where('meta_key', $value); // 0 => 'foo'
            });
        }

        return $query;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function saveMeta($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->saveOneMeta($k, $v);
            }

            $this->load('meta');

            return true;
        }

        return $this->saveOneMeta($key, $value);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    private function saveOneMeta($key, $value)
    {
        $meta = $this->meta()->where('meta_key', $key)
            ->firstOrNew(['meta_key' => $key]);

        return $meta->fill(['meta_value' => $value])->save();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function saveField($key, $value)
    {
        return $this->saveMeta($key, $value);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection
     */
    public function createMeta($key, $value = null)
    {
        if (is_array($key)) {
            $metas = collect($key)->map(function ($value, $key) {
                return $this->createOneMeta($key, $value);
            });

            $this->load('meta');

            return $metas;
        }

        return $this->createOneMeta($key, $value);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function createOneMeta($key, $value)
    {
        return $this->meta()->create([
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createField($key, $value)
    {
        return $this->createMeta($key, $value);
    }

    /**
     * @return string
     */
    private function getClassName()
    {
        $className = sprintf(
            'Corcel\\Model\\Meta\\%sMeta', $this->getCallerClassName()
        );

        return class_exists($className) ?
            $className :
            PostMeta::class;
    }

    /**
     * @return string
     */
    private function getFieldName()
    {
        $callerName = $this->getCallerClassName();

        return sprintf('%s_id', strtolower($callerName));
    }

    /**
     * @return string
     */
    private function getCallerClassName()
    {
        $class = static::class;

        if ($relation = Arr::get($this->relatedMetaClasses, $class)) {
            $class = $relation;
        }

        return (new ReflectionClass($class))->getShortName();
    }
}