<?php

namespace App\Enums;

enum LayoutType: string
{
    case Standard = 'standard';
    case TextOnly = 'text_only';
    case ImageBetweenText = 'image_between_text';
    case QuestionDiagram = 'question_diagram';
    case OptionImages = 'option_images';
    case OptionTable = 'option_table';
    case QuestionDiagramAndOptionImages = 'question_diagram_and_option_images';
    case TableFallback = 'table_fallback';
    case FullQuestionFallback = 'full_question_fallback';
    case Unknown = 'unknown';
}
