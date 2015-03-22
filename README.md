# Phail
A simple beautifier for PHP error logs

### Installation

This script only works via [php-cli](http://php.net/manual/en/features.commandline.php).

Download phail.php

Then run:
```
cat /path/to/php_error_log | php phail.php
```

Or in interactive mode:
```
tail -f /path/to/php_error_log | php phail.php
```

### Results:

Phail will output color-coded errors.

Other features include grouping similar errors together and calculating statistics.

![Screenshot of Results](https://github.com/shaggy8871/phail/blob/master/img/preview.png?raw=true)

### Contact
* Twitter: http://twitter.com/johnginsberg

### Contributions
Pull requests are welcome.
