<?php

/**
 * Parser for JSON-style formats
 *
 * @author Eilidh McAdam <eilidh.mcadam@itomig.de>
 * @version 0.1
 * @copyright 2016 ITOMIG
 */

// Global namespace code (simple interface to parser)
namespace
{
    class SlackJSONErrors
    {
        public static $errorMsg;
    }

    function cmk_inv_decode($file, $after="software")
    {
        if (!file_exists($file))
        {
            SlackJSONErrors::$errorMsg = "Could not find file " .
                $file . "\n";
            return null;
        }
        $handle = fopen($file, "rb");
        if (!$handle)
        {
            SlackJSONErrors::$errorMsg = "Could not open file " .
                $file . "\n";
            return null;
        }
        $parser = new \SlackJSON\SlackJSONParser($handle, $after);
        fclose($handle);
        if ($parser->success())
        {
            return $parser->getObject();
        }
        else
        {
            SlackJSONErrors::$errorMsg = $parser->errorMsg();
            SlackJSONErrors::$errorMsg .= "Found " .
                $parser->errorCount() . " parsing error";
            if ($parser->errorCount() > 1)
                SlackJSONErrors::$errorMsg .= "s";
            SlackJSONErrors::$errorMsg .= "\n";
            return null;
        }
    }

    function cmk_inv_last_error_msg()
    {
        return SlackJSONErrors::$errorMsg;
    }
}

namespace SlackJSON
{
// Essentially used to tag SlackJSON objects
class SlackJSON {}
class SlackJSONParser
{
    private $lexer;
    private $errors;
    private $debug = 0;
    private $stopAfter = "";
    private $end = false;
    private $recovering = false;
    private $root; // Root of PHP structure representing data
    private $errorMsg = "";

    public function __construct($fileHandle, $stopAfter = "software")
    {
        $this->stopAfter = $stopAfter;
        $this->errors = 0;
        $this->lexer = new SlackJSONLexer($fileHandle);
        $this->lexer->nextToken();
        if ($this->debug) print($this->lexer->currentToken() . "\n");
        $this->start();
    }

    public function success()
    {
        return $this->errors == 0;
    }

    public function errorCount()
    {
        return $this->errors;
    }

    public function getObject()
    {
        return $this->root;
    }

    public function errorMsg()
    {
        return $this->errorMsg;
    }

    private function mustBe($type)
    {
        if ($this->recovering)
        {
            while ($this->lexer->currentToken()->type != $type &&
                   !$this->lexer->endOfFile())
            {
                $this->lexer->nextToken();
            }
            if ($this->lexer->endOfFile()) return;
            $this->lexer->nextToken();
            $this->recovering = false;
        }
        else
        {
            if ($this->lexer->currentToken()->type == $type)
            {
                $this->lexer->nextToken();
                if ($this->debug) print($this->lexer->currentToken() . "\n");
            }
            else
            {
                $this->syntaxError($type);
            }
        }
    }

    private function is($type)
    {
        return $this->lexer->currentToken()->type == $type;
    }

    private function syntaxError($type)
    {
        if ($this->recovering) return;
        // Temporary, not optimal
        $token = $this->lexer->currentToken();
        $errStr = "(".$token->line.", ".$token->column.") : \"".$token->value.
              "\" (".$token->type.") found where ".$type." expected.\n";
        $this->errorMsg .= $errStr;
        $this->errors++;
        $this->recovering = true;
    }

    private function start()
    {
        if ($this->debug) print("start()\n");
        $this->value($this->root);
    }

    private function value(&$val)
    {
        if ($this->debug) print("value()\n");
        if      ($this->is("{"))
        {
            $val = new SlackJSON();
            $this->object($val);
        }
        else if ($this->is("["))
        {
            $val = array();
            $this->arr($val);
        }
        else if ($this->is(SlackJSONToken::STRING))
        {
            $val = $this->lexer->currentToken()->value;
            $this->mustBe(SlackJSONToken::STRING);
        }
        else if ($this->is(SlackJSONToken::NUMBER))
        { // Note: numbers are not converted from strings
            $val = $this->lexer->currentToken()->value;
            $this->mustBe(SlackJSONToken::NUMBER);
        }
        else if ($this->is(SlackJSONToken::KEYWORD))
        {
            switch (strtolower($this->lexer->currentToken()->value))
            {
            case 'true':
                $val = true;
                break;
            case 'false':
                $val = false;
                break;
            case 'null':
            case 'none':
                $val = null;
                break;
            default:
                $this->syntaxError("true, false, null or none");
                break;
            }
            $this->mustBe(SlackJSONToken::KEYWORD);
        }
        else
            $this->syntaxError("value");
    }

    private function object(&$obj)
    {
        if ($this->debug) print("object()\n");
        $this->mustBe("{");

        if ($this->is(SlackJSONToken::STRING))
        {
            $this->member($obj);
            while ($this->is(",") && !$this->end)
            {
                $this->mustBe(",");
                $this->member($obj);
            }
        }

        if (!$this->end)
            $this->mustBe("}");
    }

    private function member(&$obj)
    {
        if ($this->debug) print("member()\n");

        $objName = $this->lexer->currentToken()->value;
        $obj->{$objName} = null;

        $this->mustBe(SlackJSONToken::STRING);
        $this->mustBe(":");
        $this->value($obj->{$objName});

        if($objName == $this->stopAfter)
        {
            $this->end = true;
        }
    }

    private function arr(&$arr)
    {
        if ($this->debug) print("arr()\n");
        $this->mustBe("[");

        if ($this->is(SlackJSONToken::KEYWORD) ||
            $this->is(SlackJSONToken::STRING) ||
            $this->is(SlackJSONToken::NUMBER) ||
            $this->is("{") || $this->is("["))
        {
            $this->value($arr[]);

            while ($this->is(","))
            {
                $this->mustBe(",");
                $this->value($arr[]);
            }
        }
        
        $this->mustBe("]");
    }
}

class SlackJSONToken
{
    const STRING = "<string>";
    const NUMBER = "<number>";
    const END_OF_FILE = "<end-of-file>";
    const KEYWORD = "<keyword>";
    const INVALID_CHAR = "<invalid-char>";
    const INVALID_TOKEN = "<invalid-token>";
    
    public $type;
    public $line;
    public $column;
    public $value;

    public function __construct($type, $line, $col, $val)
    {
        $this->type = $type;
        $this->line = $line;
        $this->column = $col;
        $this->value = $val;
    }

    public function __toString()
    {
        return "SlackJSONToken: {Type=" . $this->type . "; Line=" .
            $this->line . "; Column=" . $this->column . "; Value=" .
            $this->value . "}";
    }
}

abstract class State
{
    const START       = 0;
    // Strings
    const STRING      = 1;
    const USTRING     = 15;
    const ESC_START   = 2;
    const ESC_U       = 3;
    const ESC_X       = 4;
    const STR_END     = 5;  // Final
    // Numbers
    const NEG_NUM     = 6;
    const NUM_START   = 7;  // Final
    const FRAC_START  = 8;
    const FRAC_NUM    = 9;  // Final
    const EXP_START   = 10;
    const EXP_SIGN    = 11;
    const EXP_NUM     = 12; // Final
    // Other
    const PUNCTUATION = 13; // Final
    const KEYWORD     = 14; // Final
    // Special
    const EOF         = 98; // Final
    const INVALID     = 99; // Final
}

class SlackJSONLexer
{
    private $file;
    private $char;
    private $line;
    private $column;
    private $currentToken;
    private $totalTime;
    private $getCharCalls;

    // Buffered reader state
    private $maxChunkSize = 4096;
    private $chunk = "";
    private $chunkPos = 0;
    private $chunkLen = 0;
    
    public function __construct($fHandle)
    {
        $this->file = $fHandle;
        $this->line = 1;
        $this->column = 0;
        $this->currentToken = new SlackJSONToken("", 0, 0, "");
        $this->getNextChar();
    }

    public function endOfFile()
    {
        return $this->currentToken->type == SlackJSONToken::END_OF_FILE;
    }

    public function nextToken()
    {
        if ($this->endOfFile()) return $this->currentToken;
        $token = "";
        $type = "";
        $state = State::START;
        $startLine = 0;
        $startCol = 0;
        $done = false;
        $str_delim = "";
        $hexdig = 0;
        while ($type == "")
        {
            // FSM for tokens
            switch ($state)
            {
            case State::START:
                // If non-whitespace is found, advance FSM
                if (!ctype_space($this->char))
                {
                    $startLine = $this->line;
                    $startCol = $this->column;

                    // Quotes - string start
                    if ($this->char == "\"" ||
                        $this->char == "'")
                    {
                        $str_delim = $this->char;
                        $state = State::STRING;
                    }
                    else if ($this->char == "u")
                        $state = State::USTRING;
                    else if ($this->char == "-")
                        $state = State::NEG_NUM;
                    else if (ctype_digit($this->char))
                        $state = State::NUM_START;
                    else if (strpos("{}[],:", $this->char) !== false)
                        $state = State::PUNCTUATION;
                    else if (ctype_alpha($this->char) && $this->char != "u")
                        $state = State::KEYWORD;
                    else if ($this->char == -1)
                        $state = State::EOF;
                    else
                        $state = State::INVALID;
                }
                break;
            case State::STRING:
                // Any printable character except the string delim or \
                if (ctype_print($this->char) &&
                    $this->char != $str_delim &&
                    $this->char != "\\")
                {
                    $state = State::STRING;
                }
                else if ($this->char == "\\")       $state = State::ESC_START;
                else if ($this->char == $str_delim) $state = State::STR_END;
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::USTRING:
                if ($this->char == "\"" || $this->char == "'")
                {
                    $str_delim = $this->char;
                    $state = State::STRING;
                }
                else
                    $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::ESC_START:
                if (strpos("\"'\\/bfntr", $this->char) !== false)
                {
                    $token = substr($token, 0, -1);
                    switch ($this->char)
                    {
                    case '"':
                    case '\'':
                    case '\\':
                    case '/':
                    case '\'':
                        break;
                    case 'b':
                        $this->char = chr(8);
                        break;
                    case 'f':
                        $this->char = "\f";
                        break;
                    case 'n':
                        $this->char = "\n";
                        break;
                    case 't':
                        $this->char = "\t";
                        break;
                    case 'r':
                        $this->char = "\r";
                        break;
                    }
                    $state = State::STRING;
                }
                else if ($this->char == "u")
                {
                    $hexdig = 0;
                    $state = State::ESC_U;
                }
                else if ($this->char == "x")
                {
                    $hexdig = 0;
                    $state = State::ESC_X;
                }
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::ESC_U: // Unicode escape char (\uXXXX)
                if (ctype_xdigit($this->char))
                {
                    $hexdig++;
                    if ($hexdig < 4)
                        $state = State::ESC_U;
                    else
                    {
                        // Use built-in json_decode to interpret unicode escape
                        // (seriously, it's the easiest and fastest way afaik)
                        $this->char = json_decode('"' . substr($token, -5) .
                                                  $this->char . '"');
                        $token = substr($token, 0, count($token)-6);
                        $state = State::STRING;
                    }
                }
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::ESC_X:
                if (ctype_xdigit($this->char))
                {
                    $hexdig++;
                    if ($hexdig < 2)
                        $state = State::ESC_X;
                    else
                    {
                        // Parse it and replace escape sequence with unichar
                        $this->char = chr(hexdec(substr($token, -1) .
                                      $this->char));
                        $token = substr($token, 0, count($token)-4);
                        $state = State::STRING;
                    }
                }                    
                break;
            case State::STR_END:
                $type = SlackJSONToken::STRING;
                // Chop off delimiters
                $token = substr($token, 1, count($token)-2);
                break;
            case State::NEG_NUM:
                if (ctype_digit($this->char)) $state = State::NUM_START;
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::NUM_START:
                if (ctype_digit($this->char)) $state = State::NUM_START;
                else if ($this->char == ".")  $state = State::FRAC_START;
                else if ($this->char == "e" || $this->char == "E") $state = 9;
                else
                    $type = SlackJSONToken::NUMBER;
                break;
            case State::FRAC_START:
                if (ctype_digit($this->char)) $state = State::FRAC_NUM;
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::FRAC_NUM:
                if (ctype_digit($this->char)) $state = State::FRAC_NUM;
                else if ($this->char == "e" || $this->char == "E")
                    $state = State::EXP_START;
                else $type = SlackJSONToken::NUMBER;
                break;
            case State::EXP_START:
                if ($this->char == "+" || $this->char == "-")
                    $state = State::EXP_SIGN;
                else if (ctype_digit($this->char)) $state = State::EXP_NUM;
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::EXP_SIGN:
                if (ctype_digit($this->char)) $state = State::EXP_NUM;
                else $type = SlackJSONToken::INVALID_TOKEN;
                break;
            case State::EXP_NUM:
                if (ctype_digit($this->char)) $state = State::EXP_NUM;
                else $type = SlackJSONToken::NUMBER;
                break;
            case State::PUNCTUATION:
                $type = $token;
                break;
            case State::KEYWORD:
                if (ctype_alpha($this->char)) $state = State::KEYWORD;
                else $type = SlackJSONToken::KEYWORD;
                break;
            case 98:
                $type = SlackJSONToken::END_OF_FILE;
                break;
            case 99:
                $type = SlackJSONToken::INVALID_CHAR;
                break;
            }
            
            if ($type == "")
            {
                if ($state != State::START && $state != State::USTRING)
                    $token .= $this->char;
                $this->getNextChar();
            }
        }
        $this->currentToken =
            new SlackJSONToken($type, $startLine, $startCol, $token);
        return $this->currentToken;
    }

    public function currentToken()
    {
        return $this->currentToken;
    }

    private function getNextChar()
    {
        if ($this->char == -1) return;

        if ($this->chunkPos >= $this->chunkLen)
        {
                $this->chunk = fread($this->file, $this->maxChunkSize);
                $this->chunkLen = strlen($this->chunk);
                if ($this->chunk === false || $this->chunkLen == 0)
                {
                    $this->char = -1;
                    return;
                }
                $this->chunkPos = 0;
        }
        $char = $this->chunk[$this->chunkPos++];

        $this->column++;

        $this->char = $char;
        switch ($this->char)
        {
        case "\r":
            getNextChar();
            break;
        case "\n":
            $this->line++;
            $this->column = 0;
            break;
        }
    }
}
} // End SlackJSON namespace

?>
