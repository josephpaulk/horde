<?php
/**
 * Copyright 2012-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2012-2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Stream
 */

/**
 * Object that adds convenience/utility methods to interacting with PHP
 * streams.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Stream
 */
class Horde_Stream implements Serializable
{
    /**
     * Stream resource.
     *
     * @var resource
     */
    public $stream;

    /**
     * Parse character as UTF-8 instead of single byte.
     *
     * @var boolean
     */
    public $utf8_char = false;

    /**
     * Configuration parameters.
     *
     * @var array
     */
    protected $_params;

    /**
     * Constructor.
     *
     * @param array $opts  Configuration options.
     */
    public function __construct(array $opts = array())
    {
        $this->_params = $opts;
        $this->_init();
    }

    /**
     * Initialization method.
     */
    protected function _init()
    {
        // Sane default: read-write, 0-length stream.
        if (!$this->stream) {
            $this->stream = @fopen('php://temp', 'r+');
        }
    }

    /**
     */
    public function __clone()
    {
        $data = strval($this);
        $this->stream = null;
        $this->_init();
        $this->add($data);
    }

    /**
     * @since 1.1.0
     */
    public function __toString()
    {
        return $this->getString(0);
    }

    /**
     * Adds data to the stream.
     *
     * @param mixed $data     Data to add to the stream. Can be a resource,
     *                        Horde_Stream object, or a string(-ish) value.
     * @param boolean $reset  Reset stream pointer to initial position after
     *                        adding?
     */
    public function add($data, $reset = false)
    {
        if ($reset) {
            $pos = ftell($this->stream);
        }

        if (is_resource($data)) {
            $dpos = ftell($data);
            while (!feof($data)) {
                fwrite($this->stream, fread($data, 8192));
            }
            fseek($data, $dpos);
        } elseif ($data instanceof Horde_Stream) {
            $dpos = ftell($data->stream);
            while (!feof($data->stream)) {
                fwrite($this->stream, fread($data->stream, 8192));
            }
            fseek($data->stream, $dpos);
        } else {
            fwrite($this->stream, $data);
        }

        if ($reset) {
            fseek($this->stream, $pos);
        }
    }

    /**
     * Returns the length of the data. Does not change the stream position.
     *
     * @return integer  Stream size.
     *
     * @throws Horde_Stream_Exception
     */
    public function length()
    {
        $pos = ftell($this->stream);

        if (!$this->end()) {
            throw new Horde_Stream_Exception('ERROR');
        }

        $len = ftell($this->stream);

        if (fseek($this->stream, $pos) == -1) {
            throw new Horde_Stream_Exception('ERROR');
        }

        return $len;
    }

    /**
     * Stream utility method: get a string up to a certain character (or EOF).
     *
     * @oaram string $end  The character to stop reading at.
     *
     * @return string  The string up to $end (stream is positioned after the
     *                 end character(s), all of which are stripped from the
                       return data).
     */
    public function getToChar($end)
    {
        $found_end = false;
        $out = '';

        while (($c = $this->getChar()) !== false) {
            if ($c == $end) {
                $found_end = true;
            } elseif ($found_end) {
                fseek($this->stream, strlen($c) * -1, SEEK_CUR);
                break;
            } else {
                $out .= $c;
            }
        }

        return $out;
    }

    /**
     * Return the current character without moving the pointer.
     *
     * @return string  The current character.
     */
    public function peek()
    {
        if (($c = $this->getChar()) !== false) {
            fseek($this->stream, strlen($c) * -1, SEEK_CUR);
        }

        return $c;
    }

    /**
     * Search for a character and return its position.
     *
     * @param string $char      The character to search for.
     * @param boolean $reverse  Do a reverse search?
     * @param boolean $reset    Reset the pointer to the original position?
     *
     * @return mixed  The position (integer), or null if character not found.
     */
    public function search($char, $reverse = false, $reset = true)
    {
        $pos = ftell($this->stream);
        $found_pos = null;

        if ($reverse) {
            for ($i = $pos - 1; $i >= 0; --$i) {
                fseek($this->stream, $i);
                if ($this->getChar() == $char) {
                    $found_pos = $i;
                    break;
                }
            }
        } else {
            while (($c = $this->getChar()) !== false) {
                if ($c == $char) {
                    $found_pos = ftell($this->stream) - 1;
                    break;
                }
            }
        }

        fseek(
            $this->stream,
            $reset || is_null($found_pos) ? $pos : $found_pos
        );

        return $found_pos;
    }

    /**
     * Returns the stream (or a portion of it) as a string.
     *
     * @param integer $start  The starting position. If null, starts from the
     *                        current position. If negative, starts this far
     *                        back from the current position.
     * @param integer $end    The ending position. If null, reads the entire
     *                        stream. If negative, sets ending this far from
     *                        the end of the stream.
     *
     * @return string  A string.
     */
    public function getString($start = null, $end = null)
    {
        if (!is_null($start) && ($start < 0)) {
            fseek($this->stream, $start, SEEK_CUR);
            $start = ftell($this->stream);
        }

        if (!is_null($end)) {
            $curr = is_null($start)
                ? ftell($this->stream)
                : $start;
            $end = ($end >= 0)
                ? $end - $curr + 1
                : $this->length() - $curr + $end;
            if ($end < 0) {
                $end = 0;
            }
        }

        return stream_get_contents(
            $this->stream,
            is_null($end) ? -1 : $end,
            is_null($start) ? -1 : $start
        );
    }

    /**
     * Auto-determine the EOL string.
     *
     * @since 1.3.0
     *
     * @return string  The EOL string, or null if no EOL found.
     */
    public function getEOL()
    {
        $pos = ftell($this->stream);

        $this->rewind();
        $pos2 = $this->search("\n", false, false);
        if ($pos2) {
            fseek($this->stream, -1, SEEK_CUR);
            $eol = (fgetc($this->stream) == "\r")
                ? "\r\n"
                : "\n";
        } else {
            $eol = is_null($pos2)
                ? null
                : "\n";
        }

        fseek($this->stream, $pos);

        return $eol;
    }

    /**
     * Return a character from the string.
     *
     * @since 1.4.0
     *
     * @return string  Character (single byte, or UTF-8 character if
     *                 $utf8_char is true).
     */
    public function getChar()
    {
        $char = fgetc($this->stream);
        if (!$this->utf8_char) {
            return $char;
        }

        $c = ord($char);
        if ($c < 0x80) {
            return $char;
        }

        if ($c < 0xe0) {
            $n = 1;
        } elseif ($c < 0xf0) {
            $n = 2;
        } elseif ($c < 0xf8) {
            $n = 3;
        } else {
            throw new Horde_Stream_Exception('ERROR');
        }

        for ($i = 0; $i < $n; ++$i) {
            if (($c = fgetc($this->stream)) === false) {
                throw new Horde_Stream_Exception('ERROR');
            }
            $char .= $c;
        }

        return $char;
    }

    /**
     * Return the current stream pointer position.
     *
     * @since 1.4.0
     *
     * @return mixed  The current position (integer), or false.
     */
    public function pos()
    {
        return ftell($this->stream);
    }

    /**
     * Rewind the internal stream to the beginning.
     *
     * @since 1.4.0
     *
     * @return boolean  True if successful.
     */
    public function rewind()
    {
        return rewind($this->stream);
    }

    /**
     * Move internal pointer.
     *
     * @since 1.4.0
     *
     * @param boolean $curr  If true, offset is from current position. If
     *                       false, offset is from beginning of stream.
     *
     *
     * @return boolean  True if successful.
     */
    public function seek($offset = 0, $curr = true)
    {
        return $offset
            ? (fseek($this->stream, $offset, $curr ? SEEK_CUR : SEEK_SET) === 0)
            : true;
    }

    /**
     * Move internal pointer to the end of the stream.
     *
     * @since 1.4.0
     *
     * @param integer $offset  Move this offset from the end.
     *
     * @return boolean  True if successful.
     */
    public function end($offset = 0)
    {
        return (fseek($this->stream, $offset, SEEK_END) === 0);
    }

    /**
     * Has the end of the stream been reached?
     *
     * @since 1.4.0
     *
     * @return boolean  True if the end of the stream has been reached.
     */
    public function eof()
    {
        return feof($this->stream);
    }

    /**
     * Close the stream.
     *
     * @since 1.4.0
     */
    public function close()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        $this->_params['_pos'] = ftell($this->stream);

        return json_encode(array(
            strval($this),
            $this->_params
        ));
    }

    /**
     */
    public function unserialize($data)
    {
        $this->_init();

        $data = json_decode($data, true);
        $this->add($data[0]);
        fseek($this->stream, $data[1]['_pos']);
        unset($data[1]['_pos']);
        $this->_params = $data[1];
    }

}
