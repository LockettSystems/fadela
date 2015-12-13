ABOUT
=====
Initially launched in December 2008, Fadela is a natural language processing / chatbot project and algorithmic playground.

In its present form, a Lisp-like intermediate metalanguage called FZPL is used to exchange knowledge with the system and model natural language in a way it can natively understand, with or without existing knowledge of natural language structures.

In addition to natural language processing and understanding, the project aims to accomplish sentiment analysis, artificial emotional intelligence, and ultimately strategic interaction.

FEATURES
========
* FZPL. Represents elements of conversation and sentence structures through a quasi-programming / markup language that makes extensive use of s-expressions in Polish notation.
* Parsing.  Storage of sentence structures for reuse and natural language inference.
* Use of various object reference types, simulating those of natural language.
* Logic associations.  Builds a knowledge base of facts and attempts to discover new knowledge (and inconsistencies).
* Simplification.  Recursive "compilation" of parse trees into intermediate representation objects for simpler processing.
* Sentiment analysis.  Models, analyzes, and reapplication of derived patterns.  Affects the system's language choice, interpretation of such, behavioral pattern choices, and reasoning as it attempts to influence user behavior.
* Vector analysis. Distillation of FZPL parse trees to vectors and analyzes such, attempting to identify trends and forecast behavior.
* Dynamic Avatar.  Represents sentiment states in a visual manner.
* Simple UI.  Browser, command line interfaces.  Potential for desktop and mobile applications but not a priority.
* Dynamic Parser.  Generates parsing expression grammars, and attempts to use them to parse natural language.  Rudimentary proof of concept, but an interesting concept at that.

RELEASE/DOWNLOAD
===============
Current release version is 0.2.0.

[Github Repository](https://github.com/LockettSystems/fadela)

CHANGELOG
=========
Changelog will be made available for versions that follow 0.2.0.

ROADMAP
=======
### 0.2.1
* Polishing of rewards/punishment system, as it ties into everything else.
* Give more thought to routine, subroutine, etc. identification with respect to strategic interaction.
* More sophisticated interjections based on sentiment state.  What we have is rudimentary.
* A more modular kernel, so that kernels within kernels (and scopes!) may become a reality.  No more referencing $GLOBALS.
* External unit testing module.
### 0.2.5
* SQLite integration, esp. for logic.
* A prettier and more functional avatar.
* Can FZPL develop distinct quirks?  Behavioral/linguistic differences from the user it's learning from?
### 0.3.0
* Full-fledged Web interface.
* IDE.  Autocomplete suggestions, both for FZPL and natural language, as you type.
* To mobile platform, fully graphical and interactive via avatar.

NON-GOALS
=========
* Expert system / DBMS
* Proprietary licensing on core libraries.
* Conversion of FZPL to general purpose programming language.
* Large-scale natural language processing.  Scaled applications have not been investigated, and should be considered risky.
* "Big data" style analysis, data storage, application stack; project should be a celebration of the small data processing model.

KNOWN BUGS
==========
* Assorted mostly undocumented interpreter and parser related issues.
* The HAS-A tag's various uses are not all functioning the way they should be.
* Pointer handling is a bit odd under certain use cases.
* The inquiry-driven fluctuation system is a bit buggy as of the last time I checked.  This probably needs an overhaul.
* Null and whitespace literals result in undesired behavior from interpreter
* Ambiguous actions in IS-A blocks trigger ambiguous inquiries
* Issue concerning compression of concepts (into a format for fitting into existing structures).  No further details included.
* Sentiment fluctuations are not particularly fluid and occasionally occur (or don't) inappropriately.
* Literal parser may not necessary throw exceptions under certain use cases -- need to be stricter.
* Avatar transitions may not be friendly from browser to browser.

INSTALLATION/CONFIGURATION
==========================
* Fadela currently depends on Apache 2.4.10-9 and PHP5/PHP5-cli 5.6.5.
* Clone the git repository to a desired location - ideally a public html directory.  Granted the read/write permissions are appropriate, what currently works should work out of the box.
* There are two main run modes: CLI and browser, as well as an experimental "avatar" browser mode which can be run by adding '?avatar' when running in the browser.  There is also a new interface with a dynamic avatar which is less stable which can be accessed at index2.php.
  * Command line mode can be run by running "php index.php" with the terminal in the main directory.
  * To run in a browser, move or symlink the directory in your your public html directory and request it accordingly in any modern browser.

USAGE
=====
For a crash course on the FZPL language, consult docs/fzpl_guide.txt.

By default, sessions are not persistent.  In other words, what you submit to one form will not be saved and reused by another tab/window/session.  Tweaking the lines of code in the index files can change this, but the project is not sufficiently stable where it's worthwhile -- the architecture is almost certainly bound to change dramatically somewhere at some point, and as such a clean slate for each test iteration is more ideal in this stage.

LICENSING
=========
License files can be found in docs/.

The licenses vary from file to file and are explicitly stated at the top of each, but as a rule of thumb, if it cannot function without the core 'classes/kernel' files, it's likely GPL licensed.  Otherwise, it's likely LGPL.

CONTACT
=======
[lockettanalytical (at) gmail.com](mailto:lockettanalytical@gmail.com)
