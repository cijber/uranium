# Uranium

_Best kept in a glass cabinet._

Do you feel like async is too unwieldy and annoying to type?  
Do you wake up every morning with unfulfilled promises?  
Do you wish you could stop typing `->then` or `yield`?  
Is this you? well then Uranium might be for you!

Thanks to the newly introduced [Fibers](https://wiki.php.net/rfc/fibers), Uranium can take all those worries away!

Writing async code has never been easier! want to read a file but _asynchronously_?  
with the simple API of Uranium it's never been so easy!

```php
<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Filesystem;

$data = Filesystem::slurp("/tmp/big_file");
```

This isn't asynchronous you may say, but alas, it is. all actual async logic has been hidden away to allow for hybrid
usage.

A simple example can be shown that it is with the following script

```php
<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Uranium;
use Cijber\Uranium\Time\Duration;

$stdin = Stream::stdin();

Uranium::interval(Duration::seconds(2), function() {
    echo "Hello fellow async libraries!\n";
});

while ($line = $stdin->readLine()) {
    echo "You said: " . $line;
}
```

This script will output every 2 seconds "Hello fellow async libraries!", but when you write something and press enter
it'll also output "You said: &lt;what you entered&gt;".

Magic! (_bring your own SFX_)

What's actually happening under the hood is that when Uranium is told to wait for something, it will check if there's
already an event loop running, and if not, start one!

This has its downsides, but allows for building hybrid async/sync libraries with which the user has not to worry about
it.

The biggest downside is that after your async function is done, the event loop will stop. thus make sure you clean up!

This can be a nasty downside for actual async apps, thus it's not recommended doing this for full-blown async apps. For
these `Uranium::app` can be used, which will run the event loop until no tasks are left. like so:

```php
<?php


include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Uranium;


$stdin = Stream::stdin();

Uranium::interval(Duration::seconds(2), function() {
    echo "Hello fellow async libraries!\n";
});

Uranium::app(function (){
    foreach ($stdin->lines() as $line) {
        echo "You said: " . $line;
    }
});
```

# FAQ

#### Will this work without Fibers?

yes, but poorly. every time it's supposed to wait it just runs the event loop there, thus there's a chance for recursion
issues if the stack gets too big (read `SEGFAULT`'s). for a little testing it should work though!

#### Is this stable?

It'll have a half life of _at least_ 68.9 years.

#### Is it compatible with ReactPHP or Amphp?

It's not intended to be, but it could be done, do note that Amp and React have very different architectural designs than
Uranium.

#### Will windows be supported?

Not planning to, I am however accepting PR's in that regard.

#### What event loops will be supported?

Currently, it supports the native `stream_select` loop.

Instead of supporting all 3 event based php libraries, I am instead looking at just supporting one of the Big Three.

I prefer working closely to what the kernel offers me, so this will most likely be `libev`. There's also high chance
that I make my own epoll based loop with the help of FFI.
