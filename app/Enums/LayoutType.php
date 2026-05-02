<?php

namespace App\Enums;

enum LayoutType: string
{
    case Standard = 'standard';
    case ImageBetweenText = 'image_between_text';
    case OptionImages = 'option_images';
    case OptionTable = 'option_table';
    case TableFallback = 'table_fallback';
    case FullQuestionFallback = 'full_question_fallback';
    case Unknown = 'unknown';
}
