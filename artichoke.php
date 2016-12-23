<?php
/*
    Proof-of-concept implementation of peer-action sequences
    Copyright (C) 2016  Sylvain Hallé and friends

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

// Some definitions
define("VERSION_STRING", "0.1-alpha");

// Load libs
require_once("lib/fdf.inc.php");
require_once("lib/peer.inc.php");
require_once("lib/peer-pdf.inc.php");

// Sanity check for command-line arguments
show_header();
echo "\n";
$arguments = $argv;
array_shift($arguments); // arti-choke.php
if (count($arguments) < 2)
{
  show_usage();
  exit(1);
}
$in_filename = array_shift($arguments);
if ($in_filename === "--help")
{
  show_usage();
  exit(0);
}

// Create empty keyring
$keyring = new Keyring();

// Create empty policy (to be overridden by command line)
$policy = new Policy();

// Parse command-line arguments
$action = strtolower(array_shift($arguments));
if ($action === "dump")
{
  echo "Dumping data from $in_filename\n";
  dump_data($in_filename);
}
if ($action === "check")
{
  echo "Checking $in_filename\n";
  $files = array();
  while (!empty($arguments))
  {
    $files[] = array_shift($arguments);
  }
  $keyring->addAll($files);
  echo "Found ".count($files)." public key(s)\n";
  $out = check_data($in_filename);
  if ($out)
    echo "\nEverything is OK\n";
}
if ($action === "fill")
{
  echo "Filling $in_filename\n";
  $fields = array();
  $current_key = "";
  $out_filename = preg_replace("/(.*?)\\.pdf/", "$1-filled.pdf", $in_filename);
  $peer_name = null;
  $private_key_file = null;
  while (!empty($arguments))
  {
    $arg = array_shift($arguments);
    if ($arg === "-k" || $arg === "--key")
    {
      $private_key_file = array_shift($arguments);
    }
    elseif ($arg === "-p" || $arg === "--peer")
    {
      $peer_name = array_shift($arguments);
    }
    elseif ($arg === "-o" || $arg === "--out")
    {
      $out_fileame = array_shift($arguments);
    }
    elseif ($current_key === "")
    {
      $current_key = $arg;
    }
    else
    {
      $fields[$current_key] = $arg;
      $current_key = "";
    }
  }
  if ($private_key_file === null)
  {
    echo "Missing private key file; specify one with option -k\n";
    exit(1);
  }
  if ($peer_name === null)
  {
    echo "Missing peer name; set one with option -p\n";
    exit(1);
  }
  if (empty($fields))
  {
    echo "No fields to write\n";
    exit(1);
  }
  modify_and_stamp($in_filename, $peer_name, $fields, $out_filename);
  echo "File written to $out_filename\n";
}
exit(0);

function check_data($in_filename) // {{{
{
  global $keyring;
  global $policy;
  // Get fields from PDF
  $fs = new FieldSet(get_fields($in_filename));
  // Get peer-action sequence from field "Token"
  $token_string = $fs->fields[PAS_FIELD];
  $pas = PeerActionSequence::fromString($token_string);
  unset($fs->fields[PAS_FIELD]);
  // Check peer-action sequence
  $message = "";
  $ret_val = $pas->check($keyring, $message);
  if ($ret_val >= 0)
  {
    echo "\n".$message;
    return false;
  }
  // Check actions
  $difference = check_actions($fs, $pas);
  if (!empty($difference))
  {
    echo "\nThe document's content does not match the sequence of actions:\n";
    echo "\n".format_difference($difference);
    return false;
  }
  // Check policy
  if (!$policy->check($pas))
  {
    echo "\nThe action sequence does not follow the policy";
    return false;
  }
  return true;
} // }}}

function dump_data($in_filename) // {{{
{
  $fs = new FieldSet(get_fields($in_filename));
  echo "\nForm fields\n-----------\n";
  echo $fs->toString(false);
  $pas_string = $fs->fields[PAS_FIELD];
  $pas = PeerActionSequence::fromString($pas_string);
  echo "\nPeer-action sequence\n--------------------\n";
  echo $pas->toFormattedString(true);
} // }}}

function format_difference($diff) // {{{
{
  $out = "";
  foreach ($diff as $field => $problems)
  {
    $out .= "  - Field $field has value '".$problems["observed"]."', expected '".$problems["expected"]."'\n";
  }
  return $out;
} // }}}

function show_header() // {{{
{
  echo "Arti-Choke v".VERSION_STRING.", an artifact lifecycle checker\n";
  echo "(C) 2016 Laboratoire d'informatique formelle\n";
} // }}}
  
function show_usage() // {{{
{
  echo "Usage:\n\n";
  echo "artichoke dump  filename\n";
  echo "artichoke check filename keyfile1 [keyfile2 [...]]\n";
  echo "artichoke fill  filename -k <file> -p <peername> [-o <file>] field1 value1 [...]\n";
  echo "  filename           Input PDF form\n";
  echo "  keyfile1 ...       Name(s) of public key file(s); wildcards allowed\n";
  echo "  -o <file>          Output PDF to <file>\n";
  echo "  -k <file>          Find private key in <file>\n";
  echo "  -p <peername>      Set peer's name to <peername>\n";
  echo "  field1 value1 ...  Set value of field1 to value1\n\n";
} // }}}
?>