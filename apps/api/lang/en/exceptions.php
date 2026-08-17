<?php

/**
 * Generic, cross-domain error copy shared by many exception render()
 * methods (spec section 3's namespace list doesn't name this one
 * explicitly, but "Not found."/etc. recur across a dozen domains — one
 * shared namespace avoids the same literal string being duplicated
 * into every domain's own file).
 */
return [

    'not_found' => 'Not found.',

    'upload_too_large' => 'The uploaded file is too large. Maximum allowed size is :max.',

];
