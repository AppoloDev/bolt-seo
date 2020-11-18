<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceBeforeCommaInArrayFixer;
use PhpCsFixer\Fixer\ArrayNotation\WhitespaceAfterCommaInArrayFixer;
use PhpCsFixer\Fixer\Basic\BracesFixer;
use PhpCsFixer\Fixer\Basic\Psr0Fixer;
use PhpCsFixer\Fixer\Basic\Psr4Fixer;
use PhpCsFixer\Fixer\CastNotation\LowercaseCastFixer;
use PhpCsFixer\Fixer\CastNotation\ShortScalarCastFixer;
use PhpCsFixer\Fixer\ClassNotation\FinalInternalClassFixer;
use PhpCsFixer\Fixer\ClassNotation\NoBlankLinesAfterClassOpeningFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\FunctionNotation\PhpdocToReturnTypeFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\NoLeadingImportSlashFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DeclareEqualNormalizeFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\IncrementStyleFixer;
use PhpCsFixer\Fixer\Operator\NewWithBracesFixer;
use PhpCsFixer\Fixer\Operator\TernaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMethodCasingFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAnnotationWithoutDotFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Semicolon\NoSinglelineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\Whitespace\NoTrailingWhitespaceFixer;
use SlevomatCodingStandard\Sniffs\ControlStructures\DisallowYodaComparisonSniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\CodingStandard\Fixer\Commenting\RemoveSuperfluousDocBlockWhitespaceFixer;
use Symplify\CodingStandard\Fixer\ControlStructure\RequireFollowedByAbsolutePathFixer;
use Symplify\CodingStandard\Fixer\Php\ClassStringToClassConstantFixer;
use Symplify\CodingStandard\Fixer\Property\ArrayPropertyDefaultValueFixer;
use Symplify\CodingStandard\Fixer\Strict\BlankLineAfterStrictTypesFixer;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/clean-code.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/common.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/php70.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/php71.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/psr12.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/symfony.php');

    $containerConfigurator->import(__DIR__ . '/vendor/symplify/easy-coding-standard/config/set/symfony-risky.php');

    $services = $containerConfigurator->services();

    $services->set(ClassStringToClassConstantFixer::class);

    $services->set(ArrayPropertyDefaultValueFixer::class);

    $services->set(StandaloneLineInMultilineArrayFixer::class);

    $services->set(RequireFollowedByAbsolutePathFixer::class);

    $services->set(BlankLineAfterStrictTypesFixer::class);

    $services->set(ConcatSpaceFixer::class)
        ->call('configure', [['spacing' => 'one']]);

    $services->set(RemoveSuperfluousDocBlockWhitespaceFixer::class);

    $services->set(PhpUnitMethodCasingFixer::class);

    $services->set(FinalInternalClassFixer::class);

    $services->set(MbStrFunctionsFixer::class);

    $services->set(Psr0Fixer::class);

    $services->set(Psr4Fixer::class);

    $services->set(LowercaseCastFixer::class);

    $services->set(ShortScalarCastFixer::class);

    $services->set(BlankLineAfterOpeningTagFixer::class);

    $services->set(NoLeadingImportSlashFixer::class);

    $services->set(OrderedImportsFixer::class)
        ->call('configure', [['imports_order' => ['class', 'const', 'function']]]);

    $services->set(DeclareEqualNormalizeFixer::class)
        ->call('configure', [['space' => 'none']]);

    $services->set(NewWithBracesFixer::class);

    $services->set(BracesFixer::class)
        ->call('configure', [['allow_single_line_closure' => false, 'position_after_functions_and_oop_constructs' => 'next', 'position_after_control_structures' => 'same', 'position_after_anonymous_constructs' => 'same']]);

    $services->set(NoBlankLinesAfterClassOpeningFixer::class);

    $services->set(VisibilityRequiredFixer::class)
        ->call('configure', [['elements' => ['const', 'method', 'property']]]);

    $services->set(TernaryOperatorSpacesFixer::class);

    $services->set(ReturnTypeDeclarationFixer::class);

    $services->set(NoTrailingWhitespaceFixer::class);

    $services->set(NoSinglelineWhitespaceBeforeSemicolonsFixer::class);

    $services->set(NoWhitespaceBeforeCommaInArrayFixer::class);

    $services->set(WhitespaceAfterCommaInArrayFixer::class);

    $services->set(PhpdocToReturnTypeFixer::class);

    $services->set(FullyQualifiedStrictTypesFixer::class);

    $services->set(NoSuperfluousPhpdocTagsFixer::class);

    $services->set(PhpdocLineSpanFixer::class)
        ->call('configure', [['property' => 'single']]);

    $services->set(DisallowYodaComparisonSniff::class);

    $parameters = $containerConfigurator->parameters();

    $parameters->set('cache_directory', __DIR__ . '/var/cache/ecs');

    $parameters->set('skip', [OrderedClassElementsFixer::class => null, YodaStyleFixer::class => null, IncrementStyleFixer::class => null, PhpdocAnnotationWithoutDotFixer::class => null, PhpdocSummaryFixer::class => null, PhpdocAlignFixer::class => null, 'Symplify\CodingStandard\Fixer\ControlStructure\RequireFollowedByAbsolutePathFixer' => null, 'Symplify\CodingStandard\Fixer\Property\ArrayPropertyDefaultValueFixer' => null]);
};
