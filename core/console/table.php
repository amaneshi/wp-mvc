<?php
/**
 * Utility for printing tables from commandline scripts.
 *
 * PHP versions 4 and 5
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * o Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote products
 *   derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Console
 * @package   Console_Table
 * @author    Richard Heyes <richard@phpguru.org>
 * @author    Jan Schneider <jan@horde.org>
 * @copyright 2002-2005 Richard Heyes
 * @copyright 2006-2008 Jan Schneider
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   CVS: $Id: Table.php 268934 2008-11-13 10:35:34Z yunosh $
 * @link      http://pear.php.net/package/Console_Table
 */

define('CONSOLE_TABLE_HORIZONTAL_RULE', 1);
define('CONSOLE_TABLE_ALIGN_LEFT', -1);
define('CONSOLE_TABLE_ALIGN_CENTER', 0);
define('CONSOLE_TABLE_ALIGN_RIGHT', 1);
define('CONSOLE_TABLE_BORDER_ASCII', -1);

/**
 * The main class.
 *
 * @category Console
 * @package  Console_Table
 * @author   Jan Schneider <jan@horde.org>
 * @license  http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @link     http://pear.php.net/package/Console_Table
 */
class Console_Table
{
    /**
     * The table headers.
     *
     * @var array
     */
    public $_headers = [];

    /**
     * The data of the table.
     *
     * @var array
     */
    public $_data = [];

    /**
     * The maximum number of columns in a row.
     *
     * @var integer
     */
    public $_max_cols = 0;

    /**
     * The maximum number of rows in the table.
     *
     * @var integer
     */
    public $_max_rows = 0;

    /**
     * Lengths of the columns, calculated when rows are added to the table.
     *
     * @var array
     */
    public $_cell_lengths = [];

    /**
     * Heights of the rows.
     *
     * @var array
     */
    public $_row_heights = [];

    /**
     * Column filters.
     *
     * @var array
     */
    public $_filters = [];

    /**
     * Columns to calculate totals for.
     */
    public array $_calculateTotals = [];

    /**
     * Alignment of the columns.
     *
     * @var array
     */
    public $_col_align = [];

    /**
     * Character set of the data.
     *
     * @var string
     */
    public $_charset = 'utf-8';

    /**
     * Constructor.
     *
     * @param integer $_defaultAlign Default alignment. One of
     *                         CONSOLE_TABLE_ALIGN_LEFT,
     *                         CONSOLE_TABLE_ALIGN_CENTER or
     *                         CONSOLE_TABLE_ALIGN_RIGHT.
     * @param string|int $_border The character used for table borders or
     *                         CONSOLE_TABLE_BORDER_ASCII.
     * @param integer $_padding How many spaces to use to pad the table.
     * @param string  $charset A charset supported by the mbstring PHP
     *                         extension.
     * @param boolean $_ansiColor Whether the data contains ansi color codes.
     */
    function __construct(/**
     * Default alignment of columns.
     */
    public $_defaultAlign = CONSOLE_TABLE_ALIGN_LEFT,
                         /**
                          * Border character.
                          */
                         public $_border = CONSOLE_TABLE_BORDER_ASCII, /**
                          * How many spaces to use to pad the table.
                          */
                         public $_padding = 1,
                         $charset = null, /**
                          * Whether the data has ANSI colors.
                          */
                         public $_ansiColor = false)
    {
        if ($this->_ansiColor) {
            include_once(__DIR__ . '/color.php');
        }
        if (!empty($charset)) {
            $this->setCharset($charset);
        }
    }

    /**
     * Converts an array to a table.
     *
     * @param mixed   $headers      Headers for the table, validated before use.
     * @param mixed   $data         A two dimensional array with the table
     *                              data.
     * @param boolean $returnObject Whether to return the Console_Table object
     *                              instead of the rendered table.
     *
     * @static
     *
     * @return Console_Table|string|false  A Console_Table object or the generated
     *                               table.
     */
    function fromArray($headers, $data, $returnObject = false): false|\Console_Table|string
    {
        if (!is_array($headers) || !is_array($data)) {
            return false;
        }

        $table = new Console_Table();
        $table->setHeaders($headers);

        foreach ($data as $row) {
            $table->addRow($row);
        }

        return $returnObject ? $table : $table->getTable();
    }

    /**
     * Adds a filter to a column.
     *
     * Filters are standard PHP callbacks which are run on the data before
     * table generation is performed. Filters are applied in the order they
     * are added. The callback function must accept a single argument, which
     * is a single table cell.
     *
     * @param integer $col       Column to apply filter to.
     * @param mixed   &$callback PHP callback to apply.
     */
    function addFilter($col, &$callback): void
    {
        $this->_filters[] = [$col, &$callback];
    }

    /**
     * Sets the charset of the provided table data.
     *
     * @param string $charset A charset supported by the mbstring PHP
     *                        extension.
     */
    function setCharset($charset): void
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'en_US');
        $this->_charset = strtolower($charset);
        setlocale(LC_CTYPE, $locale);
    }

    /**
     * Sets the alignment for the columns.
     *
     * @param integer $col_id The column number.
     * @param integer $align  Alignment to set for this column. One of
     *                        CONSOLE_TABLE_ALIGN_LEFT
     *                        CONSOLE_TABLE_ALIGN_CENTER
     *                        CONSOLE_TABLE_ALIGN_RIGHT.
     */
    function setAlign($col_id, $align = CONSOLE_TABLE_ALIGN_LEFT): void
    {
        $pad = match ($align) {
            CONSOLE_TABLE_ALIGN_CENTER => STR_PAD_BOTH,
            CONSOLE_TABLE_ALIGN_RIGHT => STR_PAD_LEFT,
            default => STR_PAD_RIGHT,
        };
        $this->_col_align[$col_id] = $pad;
    }

    /**
     * Specifies which columns are to have totals calculated for them and
     * added as a new row at the bottom.
     *
     * @param array $cols Array of column numbers (starting with 0).
     */
    function calculateTotalsFor(array $cols): void
    {
        $this->_calculateTotals = $cols;
    }

    /**
     * Sets the headers for the columns.
     *
     * @param array $headers The column headers.
     */
    function setHeaders($headers): void
    {
        $this->_headers = [array_values($headers)];
        $this->_updateRowsCols($headers);
    }

    /**
     * Adds a row to the table.
     *
     * @param array   $row    The row data to add.
     * @param boolean $append Whether to append or prepend the row.
     */
    function addRow($row, $append = true): void
    {
        if ($append) {
            $this->_data[] = array_values($row);
        } else {
            array_unshift($this->_data, array_values($row));
        }

        $this->_updateRowsCols($row);
    }

    /**
     * Inserts a row after a given row number in the table.
     *
     * If $row_id is not given it will prepend the row.
     *
     * @param array   $row    The data to insert.
     * @param integer $row_id Row number to insert before.
     */
    function insertRow($row, $row_id = 0): void
    {
        array_splice($this->_data, $row_id, 0, [$row]);

        $this->_updateRowsCols($row);
    }

    /**
     * Adds a column to the table.
     *
     * @param array   $col_data The data of the column.
     * @param integer $col_id   The column index to populate.
     * @param integer $row_id   If starting row is not zero, specify it here.
     */
    function addCol($col_data, $col_id = 0, $row_id = 0): void
    {
        foreach ($col_data as $col_cell) {
            $this->_data[$row_id++][$col_id] = $col_cell;
        }

        $this->_updateRowsCols();
        $this->_max_cols = max($this->_max_cols, $col_id + 1);
    }

    /**
     * Adds data to the table.
     *
     * @param array   $data   A two dimensional array with the table data.
     * @param integer $col_id Starting column number.
     * @param integer $row_id Starting row number.
     */
    function addData($data, $col_id = 0, $row_id = 0): void
    {
        foreach ($data as $row) {
            if (!is_array($row)) {
                $this->_data[$row_id] = CONSOLE_TABLE_HORIZONTAL_RULE;
                $row_id++;
                continue;
            }
            $starting_col = $col_id;
            foreach ($row as $cell) {
                $this->_data[$row_id][$starting_col++] = $cell;
            }
            $this->_updateRowsCols();
            $this->_max_cols = max($this->_max_cols, $starting_col);
            $row_id++;
        }
    }

    /**
     * Adds a horizontal seperator to the table.
     */
    function addSeparator(): void
    {
        $this->_data[] = CONSOLE_TABLE_HORIZONTAL_RULE;
    }

    /**
     * Returns the generated table.
     *
     * @return string  The generated table.
     */
    function getTable(): string
    {
        $this->_applyFilters();
        $this->_calculateTotals();
        $this->_validateTable();

        return $this->_buildTable();
    }

    /**
     * Calculates totals for columns.
     */
    function _calculateTotals(): void
    {
        if ($this->_calculateTotals === []) {
            return;
        }

        $this->addSeparator();

        $totals = [];
        foreach ($this->_data as $row) {
            if (is_array($row)) {
                foreach ($this->_calculateTotals as $columnID) {
                    $totals[$columnID] += $row[$columnID];
                }
            }
        }

        $this->_data[] = $totals;
        $this->_updateRowsCols();
    }

    /**
     * Applies any column filters to the data.
     */
    function _applyFilters(): void
    {
        if ($this->_filters === []) {
            return;
        }

        foreach ($this->_filters as $filter) {
            $column   = $filter[0];
            $callback = $filter[1];

            foreach ($this->_data as $row_id => $row_data) {
                if ($row_data !== CONSOLE_TABLE_HORIZONTAL_RULE) {
                    $this->_data[$row_id][$column] =
                        call_user_func($callback, $row_data[$column]);
                }
            }
        }
    }

    /**
     * Ensures that column and row counts are correct.
     */
    function _validateTable(): void
    {
        if ($this->_headers !== []) {
            $this->_calculateRowHeight(-1, $this->_headers[0]);
        }

        for ($i = 0; $i < $this->_max_rows; $i++) {
            for ($j = 0; $j < $this->_max_cols; $j++) {
                if (!isset($this->_data[$i][$j]) &&
                    (!isset($this->_data[$i]) ||
                     $this->_data[$i] !== CONSOLE_TABLE_HORIZONTAL_RULE)) {
                    $this->_data[$i][$j] = '';
                }

            }
            $this->_calculateRowHeight($i, $this->_data[$i]);

            if ($this->_data[$i] !== CONSOLE_TABLE_HORIZONTAL_RULE) {
                 ksort($this->_data[$i]);
            }

        }

        $this->_splitMultilineRows();
        // Update cell lengths.
        $counter = count($this->_headers);

        // Update cell lengths.
        for ($i = 0; $i < $counter; $i++) {
            $this->_calculateCellLengths($this->_headers[$i]);
        }
        for ($i = 0; $i < $this->_max_rows; $i++) {
            $this->_calculateCellLengths($this->_data[$i]);
        }

        ksort($this->_data);
    }

    /**
     * Splits multiline rows into many smaller one-line rows.
     */
    function _splitMultilineRows(): void
    {
        ksort($this->_data);
        $sections          = [&$this->_headers, &$this->_data];
        $max_rows          = [count($this->_headers), $this->_max_rows];
        $row_height_offset = [-1, 0];

        for ($s = 0; $s <= 1; $s++) {
            $inserted = 0;
            $new_data = $sections[$s];

            for ($i = 0; $i < $max_rows[$s]; $i++) {
                // Process only rows that have many lines.
                $height = $this->_row_heights[$i + $row_height_offset[$s]];
                if ($height > 1) {
                    // Split column data into one-liners.
                    $split = [];
                    for ($j = 0; $j < $this->_max_cols; $j++) {
                        $split[$j] = preg_split('/\r?\n|\r/',
                                                (string) $sections[$s][$i][$j]);
                    }

                    $new_rows = [];
                    // Construct new 'virtual' rows - insert empty strings for
                    // columns that have less lines that the highest one.
                    for ($i2 = 0; $i2 < $height; $i2++) {
                        for ($j = 0; $j < $this->_max_cols; $j++) {
                            $new_rows[$i2][$j] = $split[$j][$i2] ?? '';
                        }
                    }

                    // Replace current row with smaller rows.  $inserted is
                    // used to take account of bigger array because of already
                    // inserted rows.
                    array_splice($new_data, $i + $inserted, 1, $new_rows);
                    $inserted += count($new_rows) - 1;
                }
            }

            // Has the data been modified?
            if ($inserted > 0) {
                $sections[$s] = $new_data;
                $this->_updateRowsCols();
            }
        }
    }

    /**
     * Builds the table.
     *
     * @return string  The generated table string.
     */
    function _buildTable(): string
    {
        if (!count($this->_data)) {
            return '';
        }

        $rule      = $this->_border == CONSOLE_TABLE_BORDER_ASCII
            ? '|'
            : $this->_border;
        $separator = $this->_getSeparator();

        $return = [];
        $counter = count($this->_data);
        for ($i = 0; $i < $counter; $i++) {
            $column_count = is_array($this->_data[$i]) ? count($this->_data[$i]) : 0;
            for ($j = 0; $j < $column_count; $j++) {
                if ($this->_data[$i] !== CONSOLE_TABLE_HORIZONTAL_RULE &&
                    $this->_strlen($this->_data[$i][$j]) <
                    $this->_cell_lengths[$j]) {
                    $this->_data[$i][$j] = $this->_strpad($this->_data[$i][$j],
                                                          $this->_cell_lengths[$j],
                                                          ' ',
                                                          $this->_col_align[$j]);
                }
            }

            if ($this->_data[$i] !== CONSOLE_TABLE_HORIZONTAL_RULE) {
                $row_begin    = $rule . str_repeat(' ', $this->_padding);
                $row_end      = str_repeat(' ', $this->_padding) . $rule;
                $implode_char = str_repeat(' ', $this->_padding) . $rule
                    . str_repeat(' ', $this->_padding);
                $return[]     = $row_begin
                    . implode($implode_char, $this->_data[$i]) . $row_end;
            } elseif (!empty($separator)) {
                $return[] = $separator;
            }

        }

        $return = implode("\r\n", $return);
        if (!empty($separator)) {
            $return = $separator . "\r\n" . $return . "\r\n" . $separator;
        }
        $return .= "\r\n";

        if ($this->_headers !== []) {
            $return = $this->_getHeaderLine() .  "\r\n" . $return;
        }

        return $return;
    }

    /**
     * Creates a horizontal separator for header separation and table
     * start/end etc.
     *
     * @return string  The horizontal separator.
     */
    function _getSeparator(): ?string
    {
        if (!$this->_border) {
            return null;
        }

        if ($this->_border == CONSOLE_TABLE_BORDER_ASCII) {
            $rule = '-';
            $sect = '+';
        } else {
            $rule = $sect = (string) $this->_border;
        }

        $return = [];
        foreach ($this->_cell_lengths as $cl) {
            $return[] = str_repeat($rule, $cl);
        }

        $row_begin    = $sect . str_repeat($rule, $this->_padding);
        $row_end      = str_repeat($rule, $this->_padding) . $sect;
        $implode_char = str_repeat($rule, $this->_padding) . $sect
            . str_repeat($rule, $this->_padding);

        return $row_begin . implode($implode_char, $return) . $row_end;
    }

    /**
     * Returns the header line for the table.
     *
     * @return string  The header line of the table.
     */
    function _getHeaderLine(): string
    {
        // Make sure column count is correct
        $counter = count($this->_headers);
        // Make sure column count is correct
        for ($j = 0; $j < $counter; $j++) {
            for ($i = 0; $i < $this->_max_cols; $i++) {
                if (!isset($this->_headers[$j][$i])) {
                    $this->_headers[$j][$i] = '';
                }
            }
        }
        $counter = count($this->_headers);

        for ($j = 0; $j < $counter; $j++) {
            for ($i = 0; $i < count($this->_headers[$j]); $i++) {
                if ($this->_strlen($this->_headers[$j][$i]) <
                    $this->_cell_lengths[$i]) {
                    $this->_headers[$j][$i] =
                        $this->_strpad($this->_headers[$j][$i],
                                       $this->_cell_lengths[$i],
                                       ' ',
                                       $this->_col_align[$i]);
                }
            }
        }

        $rule         = $this->_border == CONSOLE_TABLE_BORDER_ASCII
            ? '|'
            : $this->_border;
        $row_begin    = $rule . str_repeat(' ', $this->_padding);
        $row_end      = str_repeat(' ', $this->_padding) . $rule;
        $implode_char = str_repeat(' ', $this->_padding) . $rule
            . str_repeat(' ', $this->_padding);

        $return = [];
        $separator = $this->_getSeparator();
        if (!empty($separator)) {
            $return[] = $separator;
        }
        $counter = count($this->_headers);
        for ($j = 0; $j < $counter; $j++) {
            $return[] = $row_begin
                . implode($implode_char, $this->_headers[$j]) . $row_end;
        }

        return implode("\r\n", $return);
    }

    /**
     * Updates values for maximum columns and rows.
     *
     * @param array $rowdata Data array of a single row.
     */
    function _updateRowsCols($rowdata = null): void
    {
        // Update maximum columns.
        $this->_max_cols = max($this->_max_cols, count($rowdata ?? []));

        // Update maximum rows.
        ksort($this->_data);
        $keys            = array_keys($this->_data);
        $this->_max_rows = $keys === [] ? 0 : (int) end($keys) + 1;

        $pad = match ($this->_defaultAlign) {
            CONSOLE_TABLE_ALIGN_CENTER => STR_PAD_BOTH,
            CONSOLE_TABLE_ALIGN_RIGHT => STR_PAD_LEFT,
            default => STR_PAD_RIGHT,
        };

        // Set default column alignments
        for ($i = count($this->_col_align); $i < $this->_max_cols; $i++) {
            $this->_col_align[$i] = $pad;
        }
    }

    /**
     * Calculates the maximum length for each column of a row.
     *
     * @param array|int $row The row data or horizontal-rule sentinel.
     */
    function _calculateCellLengths(array|int $row): void
    {
        if (!is_array($row)) {
            return;
        }
        $counter = count($row);
        for ($i = 0; $i < $counter; $i++) {
            if (!isset($this->_cell_lengths[$i])) {
                $this->_cell_lengths[$i] = 0;
            }
            $this->_cell_lengths[$i] = max($this->_cell_lengths[$i],
                                           $this->_strlen($row[$i]));
        }
    }

    /**
     * Calculates the maximum height for all columns of a row.
     *
     * @param integer $row_number The row number.
     * @param array|int $row      The row data or horizontal-rule sentinel.
     */
    function _calculateRowHeight($row_number, array|int $row): void
    {
        if (!isset($this->_row_heights[$row_number])) {
            $this->_row_heights[$row_number] = 1;
        }

        // Do not process horizontal rule rows.
        if (!is_array($row)) {
            return;
        }

        for ($i = 0, $c = count($row); $i < $c; ++$i) {
            $lines                           = preg_split('/\r?\n|\r/', (string) $row[$i]);
            $this->_row_heights[$row_number] = max($this->_row_heights[$row_number],
                                                   count($lines));
        }
    }

    /**
     * Returns the character length of a string.
     *
     * @param scalar|null $str A displayed cell value.
     *
     * @return integer  The string length.
     */
    function _strlen($str): int
    {
        $str = (string) $str;
        static $mbstring, $utf8;

        // Strip ANSI color codes if requested.
        if ($this->_ansiColor) {
            $str = Console_Color::strip($str) ?? '';
        }

        // Cache expensive function_exists() calls.
        if (!isset($mbstring)) {
            $mbstring = function_exists('mb_strlen');
        }
        if (!isset($utf8)) {
            $utf8 = function_exists('utf8_decode');
        }

        if ($utf8 &&
            ($this->_charset == 'utf-8' ||
             $this->_charset == 'utf8')) {
            return strlen(mb_convert_encoding($str, 'ISO-8859-1'));
        }
        if ($mbstring) {
            return mb_strlen($str, $this->_charset);
        }

        return strlen($str);
    }

    /**
     * Returns part of a string.
     *
     * @param string  $string The string to be converted.
     * @param integer $start  The part's start position, zero based.
     * @param integer $length The part's length.
     *
     * @return string  The string's part.
     */
    function _substr($string, $start, $length = null): string
    {
        static $mbstring;

        // Cache expensive function_exists() calls.
        if (!isset($mbstring)) {
            $mbstring = function_exists('mb_substr');
        }

        if (is_null($length)) {
            $length = $this->_strlen($string);
        }
        if ($mbstring) {
            $ret = @mb_substr($string, $start, $length, $this->_charset);
            if ($ret !== '' && $ret !== '0') {
                return $ret;
            }
        }
        return substr($string, $start, $length);
    }

    /**
     * Returns a string padded to a certain length with another string.
     *
     * This method behaves exactly like str_pad but is multibyte safe.
     *
     * @param string  $input  The string to be padded.
     * @param integer $length The length of the resulting string.
     * @param string  $pad    The string to pad the input string with. Must
     *                        be in the same charset like the input string.
     * @param int     $type   The padding type. One of STR_PAD_LEFT,
     *                        STR_PAD_RIGHT, or STR_PAD_BOTH.
     *
     * @return string  The padded string.
     */
    function _strpad(string $input, $length, $pad = ' ', $type = STR_PAD_RIGHT): string
    {
        $mb_length  = $this->_strlen($input);
        $sb_length  = strlen($input);
        $pad_length = $this->_strlen($pad);

        /* Return if we already have the length. */
        if ($mb_length >= $length) {
            return $input;
        }

        /* Shortcut for single byte strings. */
        if ($mb_length === $sb_length && $pad_length === strlen($pad)) {
            return str_pad($input, $length, $pad, $type);
        }

        switch ($type) {
        case STR_PAD_LEFT:
            $left   = $length - $mb_length;
            $output = $this->_substr(str_repeat($pad, (int) ceil($left / $pad_length)),
                                     0, $left) . $input;
            break;
        case STR_PAD_BOTH:
            $left   = (int) floor(($length - $mb_length) / 2);
            $right  = (int) ceil(($length - $mb_length) / 2);
            $output = $this->_substr(str_repeat($pad, (int) ceil($left / $pad_length)),
                                     0, $left) .
                $input .
                $this->_substr(str_repeat($pad, (int) ceil($right / $pad_length)),
                               0, $right);
            break;
        case STR_PAD_RIGHT:
            $right  = $length - $mb_length;
            $output = $input .
                $this->_substr(str_repeat($pad, (int) ceil($right / $pad_length)),
                               0, $right);
            break;
        default:
            throw new InvalidArgumentException('Unknown table padding type.');
        }

        return $output;
    }

}
