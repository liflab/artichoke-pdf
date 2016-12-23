Artichoke-PDF: enforcement of peer-action sequences in documents
================================================================

Artichoke is a command-line front-end to stamp and check PDF documents
that contain peer-action sequences.

## What you need

1. A public **keyring**. A keyring is a set of files containing the
   public keys of peers. By convention, make sure all these filles
   are named `public_key_xxx.pem`, where `xxx` is the peer's name. The
   script `gen-keypair.sh` allows you to generate pairs of public/private
   keys if you don't have some.

2. An initial **PDF form**. This form can have any number of fields.
   It must also have one special text field (generally hidden), called
   `Token`. Token will be used to store the peer-action sequence inside
   the PDF, and is initially empty. The file `Form.tex` gives a simple
   example of a form you can generate with `pdflatex`.

3. To run the programs: a PHP interpreter (installed in your PATH),
   a LaTeX distribution (to compile a PDF form) and [pdftk](http://pdftk.org)
   (installed in your PATH) to manipulate PDF metadata.

## To write a value in the form

Usage:

    artichoke-pdf filename fill -k keyfile -o outfile -p peername f1 v1 [f2 v2 [...]]

This will open `filename`, and write v1 to the field named f1 (and v2 to
field v2, etc.) The argument `peername` is the name of the peer doing this
action, and `keyfile` is the filename containing that peer's *private* key.
The resulting PDF will be written to `outfile`.

For example, if Alice wants to write "foo" to field "F1" of `Form.pdf`, the
command is:

    artichoke-pdf Form.pdf fill -k private_key_Alice.pem -o Form-filled.pdf -p Alice F1 foo

## To dump the contents of a form

Usage:

    artichoke-pdf filename dump

This will print the current value of all the form's fields, and display a
summary of the peer-action sequence contained in the document; this will look
like this:

	Form fields
	-----------
	F1:     baz
	F2:     bar
	
	Peer-action sequence
	--------------------
	Alice   W|F1|foo|       Rm/MRSzK...oYpROg0=
	Bob     W|F2|bar|       kEvrkC+e...bX4NO1w=
	Carl    W|F1|baz|       F3UYg+n1.../YPs3/k=

The peer-action sequence shows that Alice first wrote "foo" to field F1, then
Bob wrote "bar" to field F2, then Carl overwrote F1 with "baz". The rightmost
column is a shortened version of the digest string for each event.

## To check a form

Usage:

    artichoke-pdf filename check keyfile1 [keyfile2 [...]]

This will check the peer-action sequence in `filename`, using public keys
`keyfile1`, `keyfile2`, etc. if necessary. The keys must follow the naming
convention described earlier.

Check will perform three steps:

1. Make sure the digest of each event in the peer-action sequence is
   consistent with the action and peer name specified
2. Make sure the values of each field in the form match the result of
   applying the sequence of actions to an empty document
3. Make sure the sequence of actions follows the policy

For example:

    artichoke-pdf Form-filled.pdf check public_key*.pem

About Artichoke-PDF
===================

Artichoke-PDF is the result of research done by:

- [Sylvain Hallé](http://leduotang.ca/sylvain), Raphaël Khoury from 
  [Laboratoire d'informatique formelle](http://liflab.ca) at
  [Université du Québec à Chicoutimi](http://www.uqac.ca), Canada
- [Yliès Falcone](http://ylies.fr), Antoine El-Hokayem from
  [Laboratoire d'informatique de Grenoble](http://liglab.fr) at 
  [Université de Grenoble Alpes](http://www.univ-grenoble-alpes.fr/), France

It is an implementation of the concepts published in the following
reserach paper:

S. Hallé, R. Khoury, A. El-Hokayem, Y. Falcone. (2016).
[Decentralized Enforcement of Artifact Lifecycles](https://www.researchgate.net/publication/308863544).
Proc. EDOC 2016, IEEE Computer Society, 1-10.

You may also be interested in
[Artichoke-X](https://github.com/liflab/artichoke-x), a Java library
for manipulating peer-action sequences in many types of documents (not
just PDF).