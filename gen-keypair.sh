#! /bin/bash
#
# Generates a pair of RSA public/private keys simulating peers
# Usage: ./gen-keypair.sh <peername>
#        Where peername is the peer's name (e.g. Alfonso)
#
# This will generate private_key_Alfonso.pem and public_key_Alfonso.pem
#
openssl genpkey -algorithm RSA -out private_key_$1.pem -pkeyopt rsa_keygen_bits:1024
openssl rsa -pubout -in private_key_$1.pem -out public_key_$1.pem