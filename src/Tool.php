<?php

namespace Pdfember;

/**
 * The PDF tools of POST /tools/{tool}. Options per tool are listed at
 * https://pdfember.com/docs.
 */
enum Tool: string
{
    case Merge = 'merge';
    case AlternateMix = 'alternate-mix';
    case Organize = 'organize';
    case Split = 'split';
    case ExtractPages = 'extract-pages';
    case DeletePages = 'delete-pages';
    case SplitInHalf = 'split-in-half';
    case Rotate = 'rotate';
    case Flip = 'flip';
    case Crop = 'crop';
    case Resize = 'resize';
    case NUp = 'n-up';
    case Watermark = 'watermark';
    case PageNumbers = 'page-numbers';
    case HeaderFooter = 'header-footer';
    case Metadata = 'metadata';
    case Flatten = 'flatten';
    case RemoveAnnotations = 'remove-annotations';
    case ImagesToPdf = 'images-to-pdf';
}
