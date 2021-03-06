<?php

namespace Coduo\PHPMatcher\Matcher;

use Coduo\ToString\String;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class ArrayMatcher extends Matcher
{
    const ARRAY_PATTERN = "/^@array@$/";

    const UNBOUNDED_PATTERN = '@...@';

    /**
     * @var PropertyMatcher
     */
    private $propertyMatcher;

    /**
     * @var PropertyAccessor
     */
    private $accessor;

    /**
     * @param ValueMatcher $propertyMatcher
     */
    public function __construct(ValueMatcher $propertyMatcher)
    {
        $this->propertyMatcher = $propertyMatcher;
    }

    /**
     * {@inheritDoc}
     */
    public function match($value, $pattern)
    {
        if (!is_array($value)) {
            $this->error = sprintf("%s \"%s\" is not a valid array.", gettype($value), new String($value));
            return false;
        }

        if ($this->isArrayPattern($pattern)) {
            return true;
        }

        if (false === $this->iterateMatch($value, $pattern)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function canMatch($pattern)
    {
        return is_array($pattern) || $this->isArrayPattern($pattern);
    }

    private function isArrayPattern($pattern)
    {
        return is_string($pattern) && 0 !== preg_match(self::ARRAY_PATTERN, $pattern);
    }

    /**
     * @param  array $values
     * @param  array $patterns
     * @param string $parentPath
     * @return bool
     */
    private function iterateMatch(array $values, array $patterns, $parentPath = "")
    {
        $pattern = null;
        foreach ($values as $key => $value) {
            $path = $this->formatAccessPath($key);

            if ($this->shouldSkippValueMatchingFor($pattern)) {
                continue;
            }

            if ($this->valueExist($path, $patterns)) {
                $pattern = $this->getValueByPath($patterns, $path);
            } else {
                $this->setMissingElementInError('pattern', $this->formatFullPath($parentPath, $path));
                return false;
            }

            if ($this->shouldSkippValueMatchingFor($pattern)) {
                continue;
            }

            if ($this->valueMatchPattern($value, $pattern)) {
                continue;
            }

            if (!is_array($value) || !$this->canMatch($pattern)) {
                return false;
            }

            if ($this->isArrayPattern($pattern)) {
                continue;
            }

            if (false === $this->iterateMatch($value, $pattern, $this->formatFullPath($parentPath, $path))) {
                return false;
            }
        }

        if (!$this->isPatternValid($patterns, $values, $parentPath)) {
            return false;
        }

        return true;
    }

    /**
     * Check if pattern elements exist in value array
     *
     * @param array $pattern
     * @param array $values
     * @param $parentPath
     * @return bool
     */
    private function isPatternValid(array $pattern, array $values, $parentPath)
    {
        if (is_array($pattern)) {
            $notExistingKeys = array_diff_key($pattern, $values);

            if (count($notExistingKeys) > 0) {
                $keyNames = array_keys($notExistingKeys);
                $path = $this->formatFullPath($parentPath,  $this->formatAccessPath($keyNames[0]));
                $this->setMissingElementInError('value', $path);
                return false;
            }
        }

        return true;
    }

    /**
     * @param $value
     * @param $pattern
     * @return bool
     */
    private function valueMatchPattern($value, $pattern)
    {
        $match = $this->propertyMatcher->canMatch($pattern) &&
            true === $this->propertyMatcher->match($value, $pattern);

        if (!$match) {
            $this->error = $this->propertyMatcher->getError();
        }

        return $match;
    }

    /**
     * @param $path
     * @param $haystack
     * @return bool
     */
    private function valueExist($path, array $haystack)
    {
        return null !== $this->getPropertyAccessor()->getValue($haystack, $path);
    }

    /**
     * @param $array
     * @param $path
     * @return mixed
     */
    private function getValueByPath($array, $path)
    {
        return $this->getPropertyAccessor()->getValue($array, $path);
    }

    /**
     * @return \Symfony\Component\PropertyAccess\PropertyAccessorInterface
     */
    private function getPropertyAccessor()
    {
        if (isset($this->accessor)) {
            return $this->accessor;
        }

        $accessorBuilder = PropertyAccess::createPropertyAccessorBuilder();
        $this->accessor = $accessorBuilder->getPropertyAccessor();

        return $this->accessor;
    }

    /**
     * @param $place
     * @param $path
     */
    private function setMissingElementInError($place, $path)
    {
        $this->error = sprintf('There is no element under path %s in %s.', $path, $place);
    }

    /**
     * @param $key
     * @return string
     */
    private function formatAccessPath($key)
    {
        return sprintf("[%s]", $key);;
    }

    /**
     * @param $parentPath
     * @param $path
     * @return string
     */
    private function formatFullPath($parentPath, $path)
    {
        return sprintf("%s%s", $parentPath, $path);
    }

    /**
     * @param $lastPattern
     * @return bool
     */
    private function shouldSkippValueMatchingFor($lastPattern)
    {
        return $lastPattern === self::UNBOUNDED_PATTERN;
    }
}
