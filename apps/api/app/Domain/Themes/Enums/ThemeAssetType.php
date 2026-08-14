<?php

namespace App\Domain\Themes\Enums;

enum ThemeAssetType: string
{
    case HtmlTemplate = 'html_template';
    case VueComponent = 'vue_component';
    case Css = 'css';
    case Javascript = 'javascript';
    case Image = 'image';
    case Font = 'font';
    case Svg = 'svg';
    case JsonConfig = 'json_config';
}
