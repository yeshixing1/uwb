<?php

declare(strict_types=1);

namespace Intervention\Image\Drivers\Imagick\Modifiers;

use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use Intervention\Image\Drivers\AbstractTextModifier;
use Intervention\Image\Drivers\Imagick\FontProcessor;
use Intervention\Image\Drivers\Imagick\Frame;
use Intervention\Image\Exceptions\ColorException;
use Intervention\Image\Exceptions\FontException;
use Intervention\Image\Exceptions\RuntimeException;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Interfaces\FontInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;
use Intervention\Image\Typography\Line;

/**
 * @property Point $position
 * @property string $text
 * @property FontInterface $font
 */
class TextModifier extends AbstractTextModifier implements ModifierInterface
{
    /**
     * {@inheritdoc}
     *
     * @see ModifierInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $lines = $this->processor()->textBlock($this->text, $this->font, $this->position);
        $drawText = $this->imagickDrawText($image, $this->font);
        $drawStroke = $this->imagickDrawStroke($image, $this->font);

        foreach ($image as $frame) {
            foreach ($lines as $line) {
                foreach ($this->strokeOffsets($this->font) as $offset) {
                    // Draw the stroke outline under the actual text
                    $this->maybeDrawText($frame, $line, $drawStroke, $offset);
                }

                // Draw the actual text
                $this->maybeDrawText($frame, $line, $drawText);
            }
        }

        return $image;
    }

    /**
     * Create an ImagickDraw object to draw text on the image
     *
     * @param ImageInterface $image
     * @param FontInterface $font
     * @throws RuntimeException
     * @throws ColorException
     * @throws FontException
     * @throws ImagickDrawException
     * @throws ImagickException
     * @return ImagickDraw
     */
    private function imagickDrawText(ImageInterface $image, FontInterface $font): ImagickDraw
    {
        $color = $this->driver()->colorProcessor($image->colorspace())->colorToNative(
            $this->driver()->handleInput($font->color())
        );

        return $this->processor()->toImagickDraw($font, $color);
    }

    /**
     * Create a ImagickDraw object to draw the outline stroke effect on the Image
     *
     * @param ImageInterface $image
     * @param FontInterface $font
     * @throws RuntimeException
     * @throws ColorException
     * @throws FontException
     * @throws ImagickDrawException
     * @throws ImagickException
     * @return null|ImagickDraw
     */
    private function imagickDrawStroke(ImageInterface $image, FontInterface $font): ?ImagickDraw
    {
        if ($font->strokeWidth() <= 0) {
            return null;
        }

        $strokeColor = $this->driver()->colorProcessor($image->colorspace())->colorToNative(
            $this->driver()->handleInput($font->strokeColor())
        );

        return $this->processor()->toImagickDraw($font, $strokeColor);
    }

    /**
     * Maybe draw given line of text on frame instance depending on given
     * ImageDraw instance. Optionally move line position by given offset.
     *
     * @param Frame $frame
     * @param Line $textline
     * @param null|ImagickDraw $draw
     * @param Point $offset
     * @return void
     */
    private function maybeDrawText(
        Frame $frame,
        Line $textline,
        ?ImagickDraw $draw = null,
        Point $offset = new Point(),
    ): void {
        $frame->native()->annotateImage(
            $draw,
            $textline->position()->x() + $offset->x(),
            $textline->position()->y() + $offset->y(),
            $this->font->angle(),
            (string) $textline
        );
    }

    /**
     * Return imagick font processor
     *
     * @throws FontException
     * @return FontProcessor
     */
    private function processor(): FontProcessor
    {
        $processor = $this->driver()->fontProcessor();

        if (!($processor instanceof FontProcessor)) {
            throw new FontException('Font processor does not match the driver.');
        }

        return $processor;
    }
}
