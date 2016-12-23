<?php
include_once("fdf.inc.php");
include_once("peer.inc.php");

$original_pdf_filename = "Form.pdf";
$new_pdf_filename = "Form-filled.pdf";

/*
 * For check boxes and radio buttons, add field values as a
 * name => value pair in this array.
 */
$data_names = array();

/*
 * For text fields, combo boxes and list boxes, add field values as a 
 * name => value pair in this array
 */
$data_strings = array("Name" => "ABC");

/*
 * Any field added to the $fields_hidden or $fields_readonly array must
 * also be a key in $fdf_data_strings or $fdf_data_names; this might be
 * changed in the future.
 *
 * Any field listed in $data_strings or $data_names that you want hidden
 * or read-only must have its field name added to $hidden_fields or
 * $readonly_fields; do this even if your form has these bits set already.
 */
$hidden_fields = array();
$readonly_fields = array();

// Serialize a peer action sequence to a string
$pas = new PeerActionSequence();
$pas->appendTo("Alice", new FieldWriteAction("Name", "foo"));
$pas->appendTo("Bob", new FieldWriteAction("Name", "bar"));
$pas_string = $pas->toString();
echo $pas_string;

// Write the sequence into the "Token" hidden field in the PDF
$data_strings["Token"] = $pas_string; 

// Create FDF from values
$fdf_string = forge_fdf(null, $data_strings, $data_names, $hidden_fields, $readonly_fields);

// Write values into pdf
fill_pdf("Form.pdf", $fdf_string, "Form-filled.pdf");

// Check digest: the digest has been modified on purpose
$digest_string = "(Alice,W|Name|foo|,Y7mnPgADXEytXPbU6EIV/MaqAR1hrJVVQAQe58gLgUBu8NeV/3OgxPmnzNdBryGWGIuRDNK7YfhTcbSOCcnCOmPJwfjszbD03r7sJ23uxKH2Hcj/Ln/O3TssgmUEKqQPJw+sqcuZ/70rMxQsVIFD81wWYJ+GQ3aXVvj+XuHA0d4=)(Bob,W|Name|baz|,tVCoCayuYXTSu4A5BxVv5DpGuYZlIxlm6F1WbTS3db9FZR/ZrAnpmzSoYkCDAG7m+27o0p/ft3VTrocV1uB36ilDTNyS+ezAyyNjM3oxw6RlulVkb/QQY3DSiVDGWj/rSHcCBlhsoIUB7Vl+OBccrXEqfaqHI7Y+DQG1WpzN6js=)";
$new_pas = PeerActionSequence::fromString($digest_string);
$ret_val = $new_pas->check();

function check_all($in_filename, $start_field_set = null, $policy = null) // {{{
{
  // Get fields from PDF
  $fs = new FieldSet(get_fields($in_filename));
  // Get peer-action sequence from field "Token"
  $token_string = $fs->fields["Token"];
  $pas = PeerActionSequence::fromString($token_string);
  unset($fs->fields["Token"]);
  // Check peer-action sequence
  $ret_val = $pas->check();
  if ($ret_val >= 0)
  {
    echo "The peer-action sequence is invalid at position $ret_val";
    return false;
  }
  // Set initial field set to empty if not given
  if ($start_field_set === null)
    $start_field_set = new FieldSet();
  else
    $start_field_set = $start_field_set->getClone();
  // Apply actions
  $pas->applyAll($start_field_set);
  // Compare result with extracted field set
  if (!$fs->isEqualTo($start_field_set))
  {
    echo "The document's content does not match the sequence of actions";
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

/* :wrap=none:folding=explicit: */
?>