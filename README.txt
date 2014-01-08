The Opaque question type and behaviour

Opaque (http://docs.moodle.org/en/Development:Opaque) is the Open protocol for
accessing question engines.

The Opaque protocol was originally created by sam marshall of the Open
University (http://www.open.ac.uk/) as part of the OpenMark project
(http://java.net/projects/openmark/). The Moodle implementation of Opaque was
done by Tim Hunt.

As well as OpenMark, this question type can also be used to connect to
ounit (http://code.google.com/p/ounit/) and possibly other question systems
we don't know about.


Opaque has been available since Moodle 1.8, but this version is compatible with
Moodle 2.6+.

This question behaviour also requires the Opaque question type to be installed.

To install using git, type this command in the root of your Moodle install
    git clone -b MOODLE_25_STABLE git://github.com/moodleou/moodle-qtype_opaque.git question/type/opaque
    echo '/question/type/opaque/' >> .git/info/exclude
    git clone -b MOODLE_25_STABLE git://github.com/moodleou/moodle-qbehaviour_opaque.git question/behaviour/opaque
    echo '/question/behaviour/opaque/' >> .git/info/exclude

Alternatively, download the zip from
    https://github.com/moodleou/moodle-qtype_opaque/zipball/master
unzip it into the question/type folder, and then rename the new
folder to opaque. Then download the zip
    https://github.com/moodleou/moodle-qbehaviour_opaque/zipball/master
unzip it into the question/behaviour folder, and then rename the new
folder to opaque.


Once installed you need to go to the question type settings page
(Site administration -> Plugins -> Question types -> Opaque) to
set up the URLs of the question engines you wish to use.

https://github.com/moodleou/moodle-local_testopaqueqe can be used to test that
Opaque is working.
