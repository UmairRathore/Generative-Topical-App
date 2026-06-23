<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scientific notation formatting
    |--------------------------------------------------------------------------
    | Master switch for SciText (sub/superscripts on question + option text).
    | true  = render "H2O" as H₂O, "mol dm-3" as mol dm⁻³, etc. (default).
    | false = show the plain extracted text exactly as before.
    | Override globally here or with V2_SCI_FORMAT in .env; or per call:
    | sci($text, false) forces it off, sci($text, true) forces it on.
    */
    'sci_format' => env('V2_SCI_FORMAT', true),

    /*
    |--------------------------------------------------------------------------
    | Reference resources
    |--------------------------------------------------------------------------
    | The Periodic Table is a shared reference (not question content), offered as
    | a side panel during Chemistry exams. Public-disk path (served via the
    | storage symlink) so it is independent of any single question's images.
    */
    'periodic_table'       => 'v2/reference/periodic-table.png',
    'periodic_table_codes' => ['9701', '5070'], // subjects that get the panel

];
