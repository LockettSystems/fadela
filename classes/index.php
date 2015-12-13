<?php
/*
 * index.php - A single call-point for all files within the classes directory.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

// helper classes (LGPL)

require 'jtable.class.php';
require 'math.class.php';
require 'weightstack.class.php';

// helper subclasses (LGPL)

require 'fmr.class.php';

// misc. classes (LGPL)

require 'borx/algorithms.class.php';
require 'borx/jtraceex.php';
require 'borx/type_hinting.class.php';

// core non-kernel classes (GPL)

require 'zayane/grammar.class.php';
require 'zayane/interpreter.class.php';
require 'zayane/lang.class.php';
require 'zayane/parser.class.php';
require 'zayane/preprocessor.class.php';
require 'zayane/returntypes.class.php';
require 'zayane/scope.class.php';
require 'zayane/register.class.php';
require 'zayane/n2.class.php';
require 'zayane/bayes.class.php'; //relocate
require 'zayane/exceptions.class.php';

// kernel classes (GPL)

require 'zayane/kernel/kernel_core.class.php';
require 'zayane/kernel/kernel_basic.class.php';
	require 'zayane/kernel/builder.class.php';
	require 'zayane/kernel/fitter.class.php';
	require 'zayane/kernel/inquiry.class.php';
	require 'zayane/kernel/evaluator.class.php';
	require 'zayane/kernel/kernel_static.class.php';
require 'zayane/kernel/kernel.class.php';
require 'zayane/kernel/kernel_lib.class.php';
require 'zayane/kernel/kernel_node.class.php';

?>
