<?php
/*
    Proof-of-concept implementation of peer-action sequences
    in PDF documents
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

// Load libs
require_once("peer.inc.php");
require_once("fdf.inc.php");

/**
 * Name of the field used in the PDF to contain the sequence
 */
define("PAS_FIELD", "Token");

function modify_and_stamp($in_filename, $peer_name, $fields, $out_filename) // {{{
{
  // Get fields from PDF
  $fs = new FieldSet(get_fields($in_filename));
  // Get peer-action sequence from field "Token"
  if (isset($fs->fields[PAS_FIELD]) && $fs->fields[PAS_FIELD] !== "")
  {
    $token_string = $fs->fields[PAS_FIELD];
    $pas = PeerActionSequence::fromString($token_string);
  }
  else
  {
    $pas = new PeerActionSequence();
  }
  foreach ($fields as $name => $value)
  {
    // One field write action for each modified field
    $pas->appendTo($peer_name, new FieldWriteAction($name, $value));
  }
  $fields[PAS_FIELD] = $pas->toString();
  $fdf_string = create_fdf($fields);
  fill_pdf($in_filename, $fdf_string, $out_filename);
} // }}}

/**
 * Compares the result of a sequence of actions with the observed
 * contents of a document
 * @param $observed_fields The field set of the current document
 * @param $pas The peer-action sequence
 * @param $start_field_set Optional. The field set used as a starting
 *   point for applying the action sequence
 * @return An array of differences. The array is empty if the document
 *   is as expected
 */
function check_actions($observed_fields, $pas, $start_field_set = null) // {{{
{
  // Set initial field set to empty if not given
  if ($start_field_set === null)
    $start_field_set = new FieldSet();
  else
    $start_field_set = $start_field_set->getClone();
  // Apply actions
  $pas->applyAll($start_field_set);
  // Compare result with extracted field set
  $diff = $observed_fields->differenceWith($start_field_set);
  return $diff;
} // }}}

function check_all($in_filename, $keyring, $policy = null, $start_field_set = null) // {{{
{
  // Get fields from PDF
  $fs = new FieldSet(get_fields($in_filename));
  // Get peer-action sequence from field "Token"
  $token_string = $fs->fields[PAS_FIELD];
  $pas = PeerActionSequence::fromString($token_string);
  unset($fs->fields[PAS_FIELD]);
  // Check peer-action sequence
  $ret_val = $pas->check($keyring);
  if ($ret_val >= 0)
  {
    echo "The peer-action sequence is invalid at position $ret_val";
    return false;
  }
  // Apply actions
  $difference = check_actions($fs, $pas, $start_field_set);
  if (!empty($difference))
  {
    echo "The document's content does not match the sequence of actions";
    var_dump($difference);
    //var_dump($fs);
    //var_dump($start_field_set);
    return false;
  }
  // Check if sequence follows policy
  if ($policy !== null)
  {
    if ($policy->check($pas))
    {
      echo "The action sequence does not follow the policy";
      return false;
    }
  }
  return true;
} // }}}

?>