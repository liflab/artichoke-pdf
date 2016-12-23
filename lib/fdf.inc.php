<?php
/*
    Manipulation of PDF form fields
    Copyright (C) 2016  Sylvain Hallé
    
    Original functions by Sid Steward
    visit: www.pdfhacks.com/forge_fdf/

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
 * Fills an existing PDF with form data
 * 
 * @param $in_filename Filename of original PDF
 * @param $fdf_data Form data to put in the PDF, as produced by forge_fdf()
 * @param $out_filename Filename of output PDF
 * @param $flatten Set to true to flatten the PDF; it will no longer be
 *   editable. The default is false.
 */
function fill_pdf($in_filename, $fdf_data, $out_filename, $flatten = false) // {{{
{
  $fdf_filename = tempnam("/tmp", "FOO");
  file_put_contents($fdf_filename, $fdf_data);
  $command = "pdftk $in_filename fill_form $fdf_filename output $out_filename";
  if ($flatten)
    $command .= " flatten";
  exec($command);
  unlink($fdf_filename);
} // }}}

/**
 * Extracts PDF fields from a file. This is done by calling pdftk
 * with the "dump_data_fields" option and parsing its results back
 * into an array.
 * <p>
 * Caveat emptor: does not support nested fields.
 * 
 * @param $in_filename Filename of input PDF
 * @return An associative array of name-value pairs
 */
function get_fields($in_filename) // {{{
{
  $contents = shell_exec("pdftk $in_filename dump_data_fields");
  $lines = explode("\n", $contents);
  $f_name = "";
  $f_val = "";
  $fields = array();
  foreach ($lines as $line)
  {
    if (substr($line, 0, 3) === "---")
    {
      if ($f_name !== "")
      {
      	$fields[$f_name] = $f_val;
      }
      $f_name = "";
      $f_val = "";
    }
    if (substr($line, 0, 9) === "FieldName")
    {
      $f_name = trim(substr($line, 10));
    }
    if (substr($line, 0, 10) === "FieldValue")
    {
      $f_val = trim(substr($line, 11));
    }
  }
  if ($f_name !== "")
  {
    $fields[$f_name] = $f_val;
  }
  return $fields;
} // }}}

/**
 * Simplified version of forge_fdf
 * @param $string_fields A name-value array of fields to write
 * @param $name_fields Ditto for checkboxes and combo boxes; can be omitted
 * @return The FDF string
 */
function create_fdf($string_fields, $name_fields = null) // {{{
{
  if ($name_fields == null)
    $name_fields = array();
  $hidden_fields = array();
  $readonly_fields = array();
  return forge_fdf(null, $string_fields, $name_fields, $hidden_fields, $readonly_fields);
} // }}}

/**
 * forge_fdf, by Sid Steward
 * @version 1.1
 * @see www.pdfhacks.com/forge_fdf/
 * @param $fdf_form_url TODO, can be set to null
 * @param $fdf_data_strings For text fields, combo boxes and list boxes, add field values as a 
 *   name => value pair in this array
 * @param $fdf_data_names For check boxes and radio buttons, add field values as a
 *   name => value pair in this array.
 * @param $fdf_fields_hidden Any field added to the $fields_hidden or
 *   $fields_readonly array must
 *   also be a key in $fdf_data_strings or $fdf_data_names; this might be
 *   changed in the future. Any field listed in $data_strings or $data_names
 *   that you want hidden
 *   or read-only must have its field name added to $hidden_fields or
 *   $readonly_fields; do this even if your form has these bits set already.
 */
function forge_fdf($pdf_form_url, &$fdf_data_strings, &$fdf_data_names, &$fields_hidden, &$fields_readonly)
{
  /* 
   PDF can be particular about CR and LF characters, so I spelled them out in
   hex: CR == \x0d : LF == \x0a
  */
  $fdf = "%FDF-1.2\x0d%\xe2\xe3\xcf\xd3\x0d\x0a"; // header
  $fdf.= "1 0 obj\x0d<< "; // open the Root dictionary
  $fdf.= "\x0d/FDF << "; // open the FDF dictionary
  $fdf.= "/Fields [ "; // open the form Fields array
  
  $fdf_data_strings = burst_dots_into_arrays( $fdf_data_strings );
  forge_fdf_fields_strings( $fdf,
			  $fdf_data_strings,
			  $fields_hidden,
			  $fields_readonly );
  
  $fdf_data_names= burst_dots_into_arrays( $fdf_data_names );
  forge_fdf_fields_names( $fdf,
		    $fdf_data_names,
		    $fields_hidden,
		    $fields_readonly );
  
  $fdf.= "] \x0d"; // close the Fields array
  
  // the PDF form filename or URL, if given
  if( $pdf_form_url ) {
  $fdf.= "/F (".escape_pdf_string($pdf_form_url).") \x0d";
  }
  
  $fdf.= ">> \x0d"; // close the FDF dictionary
  $fdf.= ">> \x0dendobj\x0d"; // close the Root dictionary
  
  // trailer; note the "1 0 R" reference to "1 0 obj" above
  $fdf.= "trailer\x0d<<\x0d/Root 1 0 R \x0d\x0d>>\x0d";
  $fdf.= "%%EOF\x0d\x0a";
  
  return $fdf;
}

function escape_pdf_string( $ss )
{
  $backslash= chr(0x5c);
  $ss_esc= '';
  $ss_len= strlen( $ss );
  for( $ii= 0; $ii< $ss_len; ++$ii ) {
	if( ord($ss{$ii})== 0x28 ||  // open paren
	ord($ss{$ii})== 0x29 ||  // close paren
	ord($ss{$ii})== 0x5c )   // backslash
	  {
	$ss_esc.= $backslash.$ss{$ii}; // escape the character w/ backslash
	  }
	else if( ord($ss{$ii}) < 32 || 126 < ord($ss{$ii}) ) {
	  $ss_esc.= sprintf( "\\%03o", ord($ss{$ii}) ); // use an octal code
	}
	else {
	  $ss_esc.= $ss{$ii};
	}
  }
  return $ss_esc;
}

function escape_pdf_name( $ss ) 
{
  $ss_esc= '';
  $ss_len= strlen( $ss );
  for( $ii= 0; $ii< $ss_len; ++$ii ) {
	if( ord($ss{$ii}) < 33 || 126 < ord($ss{$ii}) || 
	ord($ss{$ii})== 0x23 ) // hash mark
	  {
	$ss_esc.= sprintf( "#%02x", ord($ss{$ii}) ); // use a hex code
	  }
	else {
	  $ss_esc.= $ss{$ii};
	}
  }
  return $ss_esc;
}

// In PDF, partial form field names are combined using periods to
// yield the full form field name; we'll take these dot-delimited
// names and then expand them into nested arrays, here; takes
// an array that uses dot-delimited names and returns a tree of arrays;
function burst_dots_into_arrays( &$fdf_data_old ) 
{
  $fdf_data_new= array();

  foreach( $fdf_data_old as $key => $value ) 
  {
    $key_split= explode( '.', (string)$key, 2 );

    if( count($key_split)== 2 ) { // handle dot
      if( !array_key_exists( (string)($key_split[0]), $fdf_data_new ) ) {
    $fdf_data_new[ (string)($key_split[0]) ]= array();
      }
      if( gettype( $fdf_data_new[ (string)($key_split[0]) ] )!= 'array' ) {
    // this new key collides with an existing name; this shouldn't happen;
    // associate string value with the special empty key in array, anyhow;

    $fdf_data_new[ (string)($key_split[0]) ]= 
      array( '' => $fdf_data_new[ (string)($key_split[0]) ] );
      }

      $fdf_data_new[ (string)($key_split[0]) ][ (string)($key_split[1]) ]= $value;
    }
    else { // no dot
      if( array_key_exists( (string)($key_split[0]), $fdf_data_new ) &&
      gettype( $fdf_data_new[ (string)($key_split[0]) ] )== 'array' )
    { // this key collides with an existing array; this shouldn't happen;
      // associate string value with the special empty key in array, anyhow;

      $fdf_data_new[ (string)$key ]['']= $value;
    }
      else { // simply copy
    $fdf_data_new[ (string)$key ]= $value;
      }
    }
  }

  foreach( $fdf_data_new as $key => $value ) 
  {
    if( gettype($value)== 'array' ) 
    {
      $fdf_data_new[ (string)$key ]= burst_dots_into_arrays( $value ); // recurse
    }
  }
  return $fdf_data_new;
}

function forge_fdf_fields_flags( &$fdf,
			$field_name,
			&$fields_hidden,
			&$fields_readonly )
{
  if( in_array( $field_name, $fields_hidden ) )
	$fdf.= "/SetF 2 "; // set
  else
	$fdf.= "/ClrF 2 "; // clear

  if( in_array( $field_name, $fields_readonly ) )
	$fdf.= "/SetFf 1 "; // set
  else
	$fdf.= "/ClrFf 1 "; // clear
}

/**
 *
 * String data is used for text fields, combo boxes and list boxes;
 * name data is used for checkboxes and radio buttons, and
 * /Yes and /Off are commonly used for true and false
 */
function forge_fdf_fields(&$fdf, &$fdf_data, &$fields_hidden, &$fields_readonly, $accumulated_name, $strings_b) // true <==> $fdf_data contains string data
{
  if(0< strlen( $accumulated_name )) 
  {
    $accumulated_name.= '.'; // append period seperator
  }

  foreach( $fdf_data as $key => $value ) 
  {
	// we use string casts to prevent numeric strings from being silently converted to numbers
	$fdf.= "<< "; // open dictionary
	if(gettype($value)== 'array') 
	{ // parent; recurse
	  $fdf.= "/T (".escape_pdf_string( (string)$key ).") "; // partial field name
	  $fdf.= "/Kids [ ";                                    // open Kids array
	  // recurse
	  forge_fdf_fields($fdf, $value, $fields_hidden, $fields_readonly, $accumulated_name. (string)$key, $strings_b);
	  $fdf.= "] "; // close Kids array
	}
	else 
	{
	  // field name
	  $fdf.= "/T (".escape_pdf_string( (string)$key ).") ";
	  // field value
	  if($strings_b)
	  { // string
	    $fdf.= "/V (".escape_pdf_string( (string)$value ).") ";
	  }
	  else 
	  { // name
	    $fdf.= "/V /".escape_pdf_name( (string)$value ). " ";
	  }
	  // field flags
	  forge_fdf_fields_flags($fdf, $accumulated_name. (string)$key, $fields_hidden, $fields_readonly);
	}
	$fdf.= ">> \x0d"; // close dictionary
  }
}

function forge_fdf_fields_strings(&$fdf, &$fdf_data_strings, &$fields_hidden, &$fields_readonly) 
{
  return forge_fdf_fields($fdf, $fdf_data_strings, $fields_hidden, $fields_readonly, '', true); // true => strings data
}

function forge_fdf_fields_names(&$fdf, &$fdf_data_names, &$fields_hidden, &$fields_readonly)
{
  return forge_fdf_fields($fdf, $fdf_data_names, $fields_hidden, $fields_readonly, '', false); // false => names data
}
?>