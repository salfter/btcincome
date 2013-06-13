btcincome
=========

This little bit of PHP provides daily and weekly graphs of income to a
Bitcoin address.  It's particularly useful for tracking mining income, but
could be used for other purposes.  To use it, put it on a webserver with PHP
support.  You'll also need phpMyGraph 5 in the same directory; it's
available from http://phpmygraph.abisvmm.nl/.

Arguments:

  addr  

    Bitcoin address for which you want to find income.
  src
  
    Bitcoin address from which you want to find income to the preceding
    address.  If null, find generation income (useful for P2Pool income).
    
  simple
  
    Since the filtering done by the src argument can take a while, you 
    can set this to any non-null value to find all income.  For an address
    that's only used for a specific purpose, this is sufficient.  You must
    still supply a non-null src argument; it will be ignored.
