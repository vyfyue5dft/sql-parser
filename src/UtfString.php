<?php
/**
 * Implementation for UTF-8 strings.
 *
 * The subscript operator in PHP, when used with string will return a byte
 * and not a character. Because in UTF-8 strings a character may occupy more
 * than one byte, the subscript operator may return an invalid character.
 *
 * Because the lexer relies on the subscript operator this class had to be
 * implemented.
 */

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser;

use ArrayAccess;
use Exception;

use function mb_check_encoding;
use function mb_strlen;
use function ord;

/**
 * Implements array-like access for UTF-8 strings.
 *
 * In this library, this class should be used to parse UTF-8 queries.
 */
class UtfString implements ArrayAccess
{
    /**
     * The raw, multi-byte string.
     *
     * @var string
     */
    public $str = '';

    /**
     * The index of current byte.
     *
     * For ASCII strings, the byte index is equal to the character index.
     *
     * @var int
     */
    public $byteIdx = 0;

    /**
     * The index of current character.
     *
     * For non-ASCII strings, some characters occupy more than one byte and
     * the character index will have a lower value than the byte index.
     *
     * @var int
     */
    public $charIdx = 0;

    /**
     * The length of the string (in bytes).
     *
     * @var int
     */
    public $byteLen = 0;

    /**
     * The length of the string (in characters).
     *
     * @var int
     */
    public $charLen = 0;

    /**
     * @param string $str the string
     */
    public function __construct($str)
    {
        $this->str = $str;
        $this->byteIdx = 0;
        $this->charIdx = 0;
        $this->byteLen = mb_strlen($str, '8bit');
        if (! mb_check_encoding($str, 'UTF-8')) {
            $this->charLen = 0;
        } else {
            $this->charLen = mb_strlen($str, 'UTF-8');
        }
    }

    /**
     * Checks if the given offset exists.
     *
     * @param int $offset the offset to be checked
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return ($offset >= 0) && ($offset < $this->charLen);
    }

    /**
     * Gets the character at given offset.
     *
     * @param int $offset the offset to be returned
     *
     * @return string|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (($offset < 0) || ($offset >= $this->charLen)) {
            return null;
        }

        $delta = $offset - $this->charIdx;

        if ($delta > 0) {
            // Fast forwarding.
            while ($delta-- > 0) {
                $this->byteIdx += static::getCharLength($this->str[$this->byteIdx]);
                ++$this->charIdx;
            }
        } elseif ($delta < 0) {
            // Rewinding.
            while ($delta++ < 0) {
                do {
                    $byte = ord($this->str[--$this->byteIdx]);
                } while (($byte >= 128) && ($byte < 192));

                --$this->charIdx;
            }
        }

        $bytesCount = static::getCharLength($this->str[$this->byteIdx]);

        $ret = '';
        for ($i = 0; $bytesCount-- > 0; ++$i) {
            $ret .= $this->str[$this->byteIdx + $i];
        }

        return $ret;
    }

    /**
     * Sets the value of a character.
     *
     * @param int    $offset the offset to be set
     * @param string $value  the value to be set
     *
     * @throws Exception not implemented.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new Exception('Not implemented.');
    }

    /**
     * Unsets an index.
     *
     * @param int $offset the value to be unset
     *
     * @throws Exception not implemented.
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new Exception('Not implemented.');
    }

    /**
     * Gets the length of an UTF-8 character.
     *
     * According to RFC 3629, a UTF-8 character can have at most 4 bytes.
     * However, this implementation supports UTF-8 characters containing up to 6
     * bytes.
     *
     * @see https://tools.ietf.org/html/rfc3629
     *
     * @param string $byte the byte to be analyzed
     *
     * @return int
     */
    public static function getCharLength($byte)
    {
        $byte = ord($byte);
        if ($byte < 128) {
            return 1;
        }

        if ($byte < 224) {
            return 2;
        }

        if ($byte < 240) {
            return 3;
        }

        if ($byte < 248) {
            return 4;
        }

        if ($byte < 252) {
            return 5; // unofficial
        }

        return 6; // unofficial
    }

    /**
     * Returns the length in characters of the string.
     *
     * @return int
     */
    public function length()
    {
        return $this->charLen;
    }

    /**
     * Returns the contained string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->str;
    }
}
