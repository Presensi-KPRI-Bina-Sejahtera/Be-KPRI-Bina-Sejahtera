<?php

namespace App\Services\Exports;

use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class OpenSpoutStyleFactory
{
    public static function thinBorder(): Border
    {
        return new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN),
        );
    }

    public static function headerStyle(?Border $border = null): Style
    {
        $style = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBackgroundColor(Color::toARGB(Color::LIGHT_BLUE));

        if ($border) {
            $style->setBorder($border);
        }

        return $style;
    }

    public static function rowStyle(?Border $border = null): Style
    {
        $style = new Style();

        if ($border) {
            $style->setBorder($border);
        }

        return $style;
    }
}
