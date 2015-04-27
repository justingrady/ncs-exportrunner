# ncs-exportrunner

## Purpose

This is a 'one use' app where NITF article files and images are exported from Saxotech web output, stored, and then transported over to a DTI web interface for parsing.  Once parsed they are imported into the new DTWeb database.

There was over seven years of content to transport totalling around 250,000 articles.  This transport was done in batches over a series of days to make sure it was working correctly.

## Parsing

Saxotech NITF --> DTI proprietary xml.  There had to be a ton of debugging with DTI as their xml spec was not well documented and many needed fields were not defined.  This led to many phone calls and e-mails back and forth.  All data was either kept using CDATA or UTF-8 encoded.

## Running

At first, articles were run a day at a time while watching error logs for garbage characters, there were many.  The _cleanser function within the exparticle controller was constantly updated until all character tests passed.
