<?php
/*
    Proof-of-concept implementation of peer-action sequences
    Copyright (C) 2016  Sylvain Hallé

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Implementation of an element of a peer-action sequence
 */
class PeerActionElement // {{{
{
  public $peer_name = "";
  public $action = null;
  public $digest = "";
  
  function __construct($peer_name, $action, $digest)
  {
    $this->peer_name = $peer_name;
    $this->action = $action;
    $this->digest = $digest;
  }
  
  public function toString() // {{{
  {
    return "{$this->peer_name},{$this->action->toString()},{$this->digest}";
  } // }}}
  
  public static function fromString($s) // {{{
  {
    list($peer_name, $action_string, $digest) = explode(",", $s);
    $action = Action::fromString($action_string);
    return new PeerActionElement($peer_name, $action, $digest);
  } // }}}
  
  public function toFormattedString($with_digest = true) // {{{
  {
    $out = "";
    $out .= $this->peer_name."\t".$this->action->toString();
    if ($with_digest)
    {
      $out .= "\t".substr($this->digest, 0, 8)."...".substr($this->digest, -8);
    }
    return $out;
  } // }}}
} // }}}

/**
 * Implementation of a peer-action sequence
 */
class PeerActionSequence // {{{
{
  public $sequence = array();
  
  /**
   * Serializes a peer-action sequence into a string to put into a PDF
   * @return A string
   */
  public function toString() // {{{
  {
    $strings = array();
    foreach ($this->sequence as $s)
    {
      $strings[] = "(".$s->toString().")";
    }
    return implode("", $strings);
  } // }}}
  
  /**
   * Serializes a peer-action sequence into a string to display on the
   * command-line
   * @param $with_digest Set to false to hide digest of each trace
   *   element (default true)
   * @return A string
   */
  public function toFormattedString($with_digest = true) // {{{
  {
    $out = "";
    for ($i = 0; $i < count($this->sequence); $i++)
    {
      $pae = $this->sequence[$i];
      echo $pae->toFormattedString($with_digest)."\n";
    }
    return $out;
  } // }}}
  
  /**
   * Creates a peer-action sequence from a character string
   * @param $s The string
   * @return A peer-action sequence created from the string
   */
  public static function fromString($s) // {{{
  {
    $matches = array();
    $pas = new PeerActionSequence();
    preg_match_all("/\\((.*?)\\)/", $s, $matches);
    for ($i = 0; $i < count($matches[1]); $i++)
    {
      $pea = PeerActionElement::fromString($matches[1][$i]);
      $pas->sequence[] = $pea;
    }
    return $pas;
  } // }}}
  
  /**
   * Checks that this peer-action sequence is valid. The sequence is
   * valid if the peers, actions and encrypted digests are consistent
   * with each other (see the paper). This does *not* check whether
   * the accompanying document matches the actions in the sequence, or
   * if the sequence satisfies the lifecycle constraints.
   *
   * @param $keyring A keyring used to decrypt digests
   * @param $message Optional. If an error occurs, the error message will
   *   be written in this variable
   * @return -1 if the sequence is valid; otherwise, the position of
   *   the element of the sequence where the first error has been found
   */
  public function check($keyring, &$message = "") // {{{
  {
    for ($i = count($this->sequence) - 1; $i > 0; $i--)
    {
      $current_element = $this->sequence[$i];
      $last_element = $this->sequence[$i - 1];
      $current_peer = $current_element->peer_name;
      $current_action = $current_element->action;
      $current_digest_crypted = base64_decode($current_element->digest);      
      // Decrypt current digest with peer's public key
      $public_key = $keyring->getPublicKey($current_peer);
      if ($public_key === null)
      {
      	$message = "Cannot find public key for $current_peer";
      	return $i;
      }
      $current_digest = "";
      openssl_public_decrypt($current_digest_crypted, $current_digest, $public_key);
      // Re-compute current digest from last element
      $last_digest = $last_element->digest;
      $expected_digest = md5($last_digest.$current_action->toString());
      // Compare
      //echo "Current $current_digest\nExpected $expected_digest\n";
      if ($current_digest !== $expected_digest)
      {
      	$message = "The peer-action sequence is invalid at position $i";
      	return $i;
      }
    }
    return -1; // Everything is OK
  } // }}}
  
  /**
   * Appends a new peer-action element to the current sequence
   * @param $peer_name The peer doing the action; this must be a peer name
   *   for which a public/private key pair has been generated
   * @param $action The name of the action
   */
  public function appendTo($peer_name, $action) // {{{
  {
    // Get last digest
    $last_digest = "0";
    if (count($this->sequence) > 0)
    {
      $last_digest = $this->sequence[count($this->sequence) - 1]->digest;
    }
    // Get private key of peer
    $private_key = file_get_contents("private_key_$peer_name.pem");
    // Compute new digest
    $new_string = md5($last_digest.$action->toString());
    $crypted = "";
    openssl_private_encrypt($new_string, $crypted, $private_key);
    $new_digest = base64_encode($crypted);
    // Add the new element to the sequence
    $this->sequence[] = new PeerActionElement($peer_name, $action, $new_digest);
  } // }}}
  
  /**
   * Applies all the actions in a sequence to an initial element.
   * This can be used to compare the current state of a document
   * with the state computed from the application of all actions.
   * @param $f The initial element. Note that this element is
   *   modified by the method call.
   */
  public function applyAll(&$f) // {{{
  {
    for ($i = 0; $i < count($this->sequence); $i++)
    {
      $pae = $this->sequence[$i];
      $pae->action->applyTo($f);
    }
  } // }}}
  
} // }}}

/**
 * Implementation of a field set
 */
class FieldSet // {{{
{
  public $fields = array();
  
  public function __construct($a = null) // {{{
  {
    if ($a !== null)
      $this->fields = $a;
  } // }}}
  
  /**
   * Produces a copy of the current field set
   * @return A copy of the current field set
   */
  public function getClone() // {{{
  {
    $out = new FieldSet();
    foreach ($this->fields as $k => $v)
    {
      $out->fields[$k] = $v;
    }
    return $out;
  } // }}}

  /**
   * Computes the difference between the current field set and another one
   * @param $fs The other field set
   * @return true if equal, false otherwise
   */
  public function differenceWith($fs) // {{{
  {
    $difference = array();
    foreach ($this->fields as $k1 => $v1)
    {
      $is_ok = true;
      $dif_el = array();
      if ($v1 !== "" && $v1 !== null)
      {
      	if (!isset($fs->fields[$k1]) || $fs->fields[$k1] !== $v1)
      	  $is_ok = false;
      }
      else
      {
      	if (isset($fs->fields[$k1]) && $fs->fields[$k1] !== "" && $fs->fields[$k1] !== null)
	{
	  $is_ok = false;
	}
      }
      if (!$is_ok)
      {
      	$difference[$k1] = array("observed" => $v1, "expected" => $fs->fields[$k1]);
      }
    }
    return $difference;
  } // }}}

  public function toString($with_token = true) // {{{
  {
    $out = "";
    foreach ($this->fields as $key => $value)
    {
      if ($key === "Token" && !$with_token)
      	continue;
      $out .= "$key:\t$value\n";
    }
    return $out;
  } // }}}

} // }}}

/**
 * Implementation of an action
 */
class Action // {{{
{
  /**
   * Applies the action to the object
   * @param $f The object. It is modified by the method.
   */
  public function applyTo(&$f) // {{{
  {
    return;
  } // }}}
  
  public function toString()
  {
    return "";
  }
  
  public static function fromString($s) // {{{
  {
    $out = null;
    $out = FieldWriteAction::fromString($s);
    if ($out !== null)
      return $out;
    return null;
  } // }}}
} // }}}

/**
 * Implementation of the action of writing to a field
 */
class FieldWriteAction extends Action // {{{
{
  private $field_name = "";
  private $field_value = "";
  
  public function __construct($field_name, $field_value) // {{{
  {
    $this->field_name = $field_name;
    $this->field_value = $field_value;
  } // }}}
  
  public function toString() // {{{
  {
    return "W|{$this->field_name}|{$this->field_value}|";
  } // }}}
  
  public static function fromString($s) // {{{
  {
    if (substr($s, 0, 1) !== "W")
      return null;
    $fields = explode("|", $s);
    $fwa = new FieldWriteAction($fields[1], $fields[2]);
    return $fwa;
  } // }}}

  /**
   * Applies the action to the object
   * @param $f The object. It is modified by the method.
   */
  public function applyTo(&$f) // {{{
  {
    $f->fields[$this->field_name] = $this->field_value;
  } // }}}
} // }}}

/**
 * Implementation of a policy. A policy checks whether the
 * sequence of peer-actions is correct.
 */
class Policy // {{{
{
  public function check($sequence) // {{{
  {
    return true;
  } // }}}
} // }}}

/**
 * Implementation of a keyring. A keyring is simply a map between
 * peer names and public keys.
 */
class Keyring // {{{
{
  public $keys = array();
  
  /**
   * Adds a public key to a keyring. The peer's name is inferred from
   * the filename; its format is public_key_xxx.pem, where xxx is the
   * peer's name.
   * @param $filename The filename
   */
  public function addKey($filename) // {{{
  {
    preg_match("/public_key_(.*?)\\./", $filename, $matches);
    $peer_name = $matches[1];
    $this->keys[$peer_name] = $filename;
  }// }}}
  
  /**
   * Adds public keys to a keyring from a list of filenames
   * @param $filenames The list of filenames
   */
  public function addAll($filenames) // {{{
  {
    foreach ($filenames as $fn)
    {
      $this->addKey($fn);
    }
  } // }}}
  
  /**
   * Retrieves the public key of a peer
   * @param $peer_name The peer's name
   * @return A string containing the public key, null if key not found
   */
  public function getPublicKey($peer_name) // {{{
  {
    if (!isset($this->keys[$peer_name]))
      return null;
    return file_get_contents($this->keys[$peer_name]);
  } // }}}
} // }}}

/* :wrap=none:folding=explicit: */
?>