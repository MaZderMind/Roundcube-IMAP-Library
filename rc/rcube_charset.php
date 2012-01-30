<?php
/*
 +-----------------------------------------------------------------------+
 | parts of program/include/main.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2011, The Roundcube Dev Team                       |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide basic functions for the webmail package                     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+

 $Id: main.inc 5715 2012-01-05 10:28:24Z alec $

*/

/**
 * Convert a string from one charset to another.
 * Uses mbstring and iconv functions if possible
 *
 * @param  string Input string
 * @param  string Suspected charset of the input string
 * @param  string Target charset to convert to; defaults to RCMAIL_CHARSET
 * @return string Converted string
 */
function rcube_charset_convert($str, $from, $to=NULL)
{
  static $iconv_options = null;
  static $mbstring_loaded = null;
  static $mbstring_list = null;
  static $conv = null;

  $error = false;

  $to = empty($to) ? strtoupper(RCMAIL_CHARSET) : rcube_parse_charset($to);
  $from = rcube_parse_charset($from);

  if ($from == $to || empty($str) || empty($from))
    return $str;

  // convert charset using iconv module
  if (function_exists('iconv') && $from != 'UTF7-IMAP' && $to != 'UTF7-IMAP') {
    if ($iconv_options === null) {
      // ignore characters not available in output charset
      $iconv_options = '//IGNORE';
      if (iconv('', $iconv_options, '') === false) {
        // iconv implementation does not support options
        $iconv_options = '';
      }
    }

    // throw an exception if iconv reports an illegal character in input
    // it means that input string has been truncated
    //set_error_handler('rcube_error_handler', E_NOTICE);
    try {
      $_iconv = iconv($from, $to . $iconv_options, $str);
    } catch (ErrorException $e) {
      $_iconv = false;
    }
    restore_error_handler();
    if ($_iconv !== false) {
      return $_iconv;
    }
  }

  if ($mbstring_loaded === null)
    $mbstring_loaded = extension_loaded('mbstring');

  // convert charset using mbstring module
  if ($mbstring_loaded) {
    $aliases['WINDOWS-1257'] = 'ISO-8859-13';

    if ($mbstring_list === null) {
      $mbstring_list = mb_list_encodings();
      $mbstring_list = array_map('strtoupper', $mbstring_list);
    }

    $mb_from = $aliases[$from] ? $aliases[$from] : $from;
    $mb_to = $aliases[$to] ? $aliases[$to] : $to;

    // return if encoding found, string matches encoding and convert succeeded
    if (in_array($mb_from, $mbstring_list) && in_array($mb_to, $mbstring_list)) {
      if (mb_check_encoding($str, $mb_from) && ($out = mb_convert_encoding($str, $mb_to, $mb_from)))
        return $out;
    }
  }

  // convert charset using bundled classes/functions
  if ($to == 'UTF-8') {
    if ($from == 'UTF7-IMAP') {
      if ($_str = utf7_to_utf8($str))
        return $_str;
    }
    else if ($from == 'UTF-7') {
      if ($_str = rcube_utf7_to_utf8($str))
        return $_str;
    }
    else if (($from == 'ISO-8859-1') && function_exists('utf8_encode')) {
      return utf8_encode($str);
    }
    else if (class_exists('utf8')) {
      if (!$conv)
        $conv = new utf8($from);
      else
        $conv->loadCharset($from);

      if($_str = $conv->strToUtf8($str))
        return $_str;
    }
    $error = true;
  }

  // encode string for output
  if ($from == 'UTF-8') {
    // @TODO: we need a function for UTF-7 (RFC2152) conversion
    if ($to == 'UTF7-IMAP' || $to == 'UTF-7') {
      if ($_str = utf8_to_utf7($str))
        return $_str;
    }
    else if ($to == 'ISO-8859-1' && function_exists('utf8_decode')) {
      return utf8_decode($str);
    }
    else if (class_exists('utf8')) {
      if (!$conv)
        $conv = new utf8($to);
      else
        $conv->loadCharset($from);

      if ($_str = $conv->strToUtf8($str))
        return $_str;
    }
    $error = true;
  }

  // return UTF-8 or original string
  return $str;
}


/**
 * Parse and validate charset name string (see #1485758).
 * Sometimes charset string is malformed, there are also charset aliases 
 * but we need strict names for charset conversion (specially utf8 class)
 *
 * @param  string Input charset name
 * @return string The validated charset name
 */
function rcube_parse_charset($input)
{
  static $charsets = array();
  $charset = strtoupper($input);

  if (isset($charsets[$input]))
    return $charsets[$input];

  $charset = preg_replace(array(
    '/^[^0-9A-Z]+/',    // e.g. _ISO-8859-JP$SIO
    '/\$.*$/',          // e.g. _ISO-8859-JP$SIO
    '/UNICODE-1-1-*/',  // RFC1641/1642
    '/^X-/',            // X- prefix (e.g. X-ROMAN8 => ROMAN8)
    ), '', $charset);

  if ($charset == 'BINARY')
    return $charsets[$input] = null;

  # Aliases: some of them from HTML5 spec.
  $aliases = array(
    'USASCII'       => 'WINDOWS-1252',
    'ANSIX31101983' => 'WINDOWS-1252',
    'ANSIX341968'   => 'WINDOWS-1252',
    'UNKNOWN8BIT'   => 'ISO-8859-15',
    'UNKNOWN'       => 'ISO-8859-15',
    'USERDEFINED'   => 'ISO-8859-15',
    'KSC56011987'   => 'EUC-KR',
    'GB2312' 	    => 'GBK',
    'GB231280'	    => 'GBK',
    'UNICODE'	    => 'UTF-8',
    'UTF7IMAP'	    => 'UTF7-IMAP',
    'TIS620'	    => 'WINDOWS-874',
    'ISO88599'	    => 'WINDOWS-1254',
    'ISO885911'	    => 'WINDOWS-874',
    'MACROMAN'	    => 'MACINTOSH',
    '77'            => 'MAC',
    '128'           => 'SHIFT-JIS',
    '129'           => 'CP949',
    '130'           => 'CP1361',
    '134'           => 'GBK',
    '136'           => 'BIG5',
    '161'           => 'WINDOWS-1253',
    '162'           => 'WINDOWS-1254',
    '163'           => 'WINDOWS-1258',
    '177'           => 'WINDOWS-1255',
    '178'           => 'WINDOWS-1256',
    '186'           => 'WINDOWS-1257',
    '204'           => 'WINDOWS-1251',
    '222'           => 'WINDOWS-874',
    '238'           => 'WINDOWS-1250',
    'MS950'         => 'CP950',
    'WINDOWS949'    => 'UHC',
  );

  // allow A-Z and 0-9 only
  $str = preg_replace('/[^A-Z0-9]/', '', $charset);

  if (isset($aliases[$str]))
    $result = $aliases[$str];
  // UTF
  else if (preg_match('/U[A-Z][A-Z](7|8|16|32)(BE|LE)*/', $str, $m))
    $result = 'UTF-' . $m[1] . $m[2];
  // ISO-8859
  else if (preg_match('/ISO8859([0-9]{0,2})/', $str, $m)) {
    $iso = 'ISO-8859-' . ($m[1] ? $m[1] : 1);
    // some clients sends windows-1252 text as latin1,
    // it is safe to use windows-1252 for all latin1
    $result = $iso == 'ISO-8859-1' ? 'WINDOWS-1252' : $iso;
  }
  // handle broken charset names e.g. WINDOWS-1250HTTP-EQUIVCONTENT-TYPE
  else if (preg_match('/(WIN|WINDOWS)([0-9]+)/', $str, $m)) {
    $result = 'WINDOWS-' . $m[2];
  }
  // LATIN
  else if (preg_match('/LATIN(.*)/', $str, $m)) {
    $aliases = array('2' => 2, '3' => 3, '4' => 4, '5' => 9, '6' => 10,
        '7' => 13, '8' => 14, '9' => 15, '10' => 16,
        'ARABIC' => 6, 'CYRILLIC' => 5, 'GREEK' => 7, 'GREEK1' => 7, 'HEBREW' => 8);

    // some clients sends windows-1252 text as latin1,
    // it is safe to use windows-1252 for all latin1
    if ($m[1] == 1) {
      $result = 'WINDOWS-1252';
    }
    // if iconv is not supported we need ISO labels, it's also safe for iconv
    else if (!empty($aliases[$m[1]])) {
      $result = 'ISO-8859-'.$aliases[$m[1]];
    }
    // iconv requires convertion of e.g. LATIN-1 to LATIN1
    else {
      $result = $str;
    }
  }
  else {
    $result = $charset;
  }

  $charsets[$input] = $result;

  return $result;
}







/*
 *  Copyright (C) 2000 Edmund Grimley Evans <edmundo@rano.org>
 * 
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 * 
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  Translated from C to PHP by Thomas Bruederli <roundcube@gmail.com>
 */ 


/**
 * Convert the data ($str) from RFC 2060's UTF-7 to UTF-8.
 * If input data is invalid, return the original input string.
 * RFC 2060 obviously intends the encoding to be unique (see
 * point 5 in section 5.1.3), so we reject any non-canonical
 * form, such as &ACY- (instead of &-) or &AMA-&AMA- (instead
 * of &AMAAwA-).
 */
function utf7_to_utf8($str)
{
  $Index_64 = array(
      -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1,
      -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1,
      -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,62, 63,-1,-1,-1,
      52,53,54,55, 56,57,58,59, 60,61,-1,-1, -1,-1,-1,-1,
      -1, 0, 1, 2,  3, 4, 5, 6,  7, 8, 9,10, 11,12,13,14,
      15,16,17,18, 19,20,21,22, 23,24,25,-1, -1,-1,-1,-1,
      -1,26,27,28, 29,30,31,32, 33,34,35,36, 37,38,39,40,
      41,42,43,44, 45,46,47,48, 49,50,51,-1, -1,-1,-1,-1
  );

  $u7len = strlen($str);
  $str = strval($str);
  $p = $err = '';

  for ($i=0; $u7len > 0; $i++, $u7len--)
  {
    $u7 = $str[$i];
    if ($u7 == '&')
    {
      $i++;
      $u7len--;
      $u7 = $str[$i];
      
      if ($u7len && $u7 == '-')
      {
        $p .= '&';
        continue;
      }

      $ch = 0;
      $k = 10;
      for (; $u7len > 0; $i++, $u7len--)
      {
        $u7 = $str[$i];

        if ((ord($u7) & 0x80) || ($b = $Index_64[ord($u7)]) == -1)
          break;

        if ($k > 0)
        {
          $ch |= $b << $k;
          $k -= 6;
        }
        else
        {
          $ch |= $b >> (-$k);
          if ($ch < 0x80)
          {
            /* Printable US-ASCII */
            if (0x20 <= $ch && $ch < 0x7f)
              return $err;
           $p .= chr($ch);
          }
          else if ($ch < 0x800)
          {
            $p .= chr(0xc0 | ($ch >> 6));
            $p .= chr(0x80 | ($ch & 0x3f));
          }
          else
          {
            $p .= chr(0xe0 | ($ch >> 12));
            $p .= chr(0x80 | (($ch >> 6) & 0x3f));
            $p .= chr(0x80 | ($ch & 0x3f));
          }

          $ch = ($b << (16 + $k)) & 0xffff;
          $k += 10;
        }
      }

      /* Non-zero or too many extra bits */
      if ($ch || $k < 6)
        return $err;
        
      /* BASE64 not properly terminated */
      if (!$u7len || $u7 != '-')
        return $err;
        
      /* Adjacent BASE64 sections */
      if ($u7len > 2 && $str[$i+1] == '&' && $str[$i+2] != '-')
        return $err;
    }
    /* Not printable US-ASCII */
    else if (ord($u7) < 0x20 || ord($u7) >= 0x7f)
      return $err;
    else
      $p .= $u7;
  }

  return $p;
}


/**
 * Convert the data ($str) from UTF-8 to RFC 2060's UTF-7.
 * Unicode characters above U+FFFF are replaced by U+FFFE.
 * If input data is invalid, return an empty string.
 */
function utf8_to_utf7($str)
{
  $B64Chars = array(
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
    'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd',
    'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
    't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7',
    '8', '9', '+', ','
  );

  $u8len = strlen($str);
  $base64 = $i = 0;
  $p = $err = '';

  while ($u8len)
  {
    $u8 = $str[$i];
    $c = ord($u8);
    
    if ($c < 0x80)
    {
      $ch = $c;
      $n = 0;
    }
    else if ($c < 0xc2)
      return $err;
    else if ($c < 0xe0)
    {
      $ch = $c & 0x1f;
      $n = 1;
    }
    else if ($c < 0xf0)
    {
      $ch = $c & 0x0f;
      $n = 2;
    }
    else if ($c < 0xf8)
    {
      $ch = $c & 0x07;
      $n = 3;
    }
    else if ($c < 0xfc)
    {
      $ch = $c & 0x03;
      $n = 4;
    }
    else if ($c < 0xfe)
    {
      $ch = $c & 0x01;
      $n = 5;
    }
    else
      return $err;

    $i++;
    $u8len--;

    if ($n > $u8len)
      return $err;

    for ($j=0; $j < $n; $j++)
    {
      $o = ord($str[$i+$j]);
      if (($o & 0xc0) != 0x80)
        return $err;
      $ch = ($ch << 6) | ($o & 0x3f);
    }
    
    if ($n > 1 && !($ch >> ($n * 5 + 1)))
      return $err;
    
    $i += $n;
    $u8len -= $n;

    if ($ch < 0x20 || $ch >= 0x7f)
    {
      if (!$base64)
      {
        $p .= '&';
        $base64 = 1;
        $b = 0;
        $k = 10;
      }
      if ($ch & ~0xffff)
        $ch = 0xfffe;
      
      $p .= $B64Chars[($b | $ch >> $k)];
      $k -= 6;
      for (; $k >= 0; $k -= 6)
        $p .= $B64Chars[(($ch >> $k) & 0x3f)];

      $b = ($ch << (-$k)) & 0x3f;
      $k += 16;
    }
    else
    {
      if ($base64)
      {
        if ($k > 10)
          $p .= $B64Chars[$b];
        $p .= '-';
        $base64 = 0;
      }
      
      $p .= chr($ch);
      if (chr($ch) == '&')
        $p .= '-';
    }
  }

  if ($base64)
  {
    if ($k > 10)
      $p .= $B64Chars[$b];
    $p .= '-';
  }

  return $p;
}

?>