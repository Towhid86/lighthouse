<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Definition\Type;
use GraphQL\Utils\SchemaPrinter;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\DirectiveNamespacer;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class IdeHelperCommand extends Command
{
    public const OPENING_PHP_TAG = "<?php\n";

    public const GENERATED_NOTICE = <<<'SDL'
# File generated by "php artisan lighthouse:ide-helper".
# Do not edit this file directly.
# This file should be ignored by git as it can be autogenerated.

SDL;

    protected $name = 'lighthouse:ide-helper';

    protected $description = 'Create IDE helper files to improve type checking and autocompletion.';

    public function handle(DirectiveNamespacer $directiveNamespaces, TypeRegistry $typeRegistry): int
    {
        if (! class_exists('HaydenPierce\ClassFinder\ClassFinder')) {
            $this->error(
                "This command requires haydenpierce/class-finder. Install it by running:\n"
                ."\n"
                ."    composer require --dev haydenpierce/class-finder\n"
            );

            return 1;
        }

        $this->schemaDirectiveDefinitions($directiveNamespaces);
        $this->programmaticTypes($typeRegistry);
        $this->phpIdeHelper();

        $this->info("\nIt is recommended to add them to your .gitignore file.");

        return 0;
    }

    /**
     * Create and write schema directive definitions to a file.
     */
    protected function schemaDirectiveDefinitions(DirectiveNamespacer $directiveNamespaces): void
    {
        $directiveClasses = $this->scanForDirectives(
            $directiveNamespaces->gather()
        );

        $schema = $this->buildSchemaString($directiveClasses);

        $filePath = static::schemaDirectivesPath();
        file_put_contents($filePath, self::GENERATED_NOTICE.$schema);

        $this->info("Wrote schema directive definitions to $filePath.");
    }

    /**
     * Scan the given namespaces for directive classes.
     *
     * @param  string[]  $directiveNamespaces
     * @return array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>
     */
    protected function scanForDirectives(array $directiveNamespaces): array
    {
        $directives = [];

        foreach ($directiveNamespaces as $directiveNamespace) {
            /** @var array<class-string> $classesInNamespace */
            $classesInNamespace = ClassFinder::getClassesInNamespace($directiveNamespace);

            foreach ($classesInNamespace as $class) {
                $reflection = new \ReflectionClass($class);
                if (! $reflection->isInstantiable()) {
                    continue;
                }

                if (! is_a($class, Directive::class, true)) {
                    continue;
                }
                /** @var class-string<\Nuwave\Lighthouse\Support\Contracts\Directive> $class */
                $name = DirectiveFactory::directiveName($class);

                // The directive was already found, so we do not add it twice
                if (isset($directives[$name])) {
                    continue;
                }

                $directives[$name] = $class;
            }
        }

        return $directives;
    }

    /**
     * @param  array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>  $directiveClasses
     */
    protected function buildSchemaString(array $directiveClasses): string
    {
        $schema = '';

        foreach ($directiveClasses as $name => $directiveClass) {
            $definition = $this->define($name, $directiveClass);

            $schema .= "\n"
                ."# Directive class: $directiveClass\n"
                .$definition."\n";
        }

        return $schema;
    }

    protected function define(string $name, string $directiveClass): string
    {
        /** @var \Nuwave\Lighthouse\Support\Contracts\Directive $directiveClass */
        $definition = $directiveClass::definition();

        // This operation throws if the schema definition is invalid
        PartialParser::directiveDefinition($definition);

        return trim($definition);
    }

    public static function schemaDirectivesPath(): string
    {
        return base_path().'/schema-directives.graphql';
    }

    protected function programmaticTypes(TypeRegistry $typeRegistry): void
    {
        // Users may register types programmatically, e.g. in service providers
        // In order to allow referencing those in the schema, it is useful to print
        // those types to a helper schema, excluding types the user defined in the schema
        $types = new Collection($typeRegistry->resolvedTypes());

        $filePath = static::programmaticTypesPath();

        if ($types->isEmpty() && file_exists($filePath)) {
            unlink($filePath);

            return;
        }

        $schema = $types
            ->map(function (Type $type): string {
                return SchemaPrinter::printType($type);
            })
            ->implode("\n");

        file_put_contents($filePath, self::GENERATED_NOTICE.$schema);

        $this->info("Wrote definitions for programmatically registered types to $filePath.");
    }

    public static function programmaticTypesPath(): string
    {
        return base_path().'/programmatic-types.graphql';
    }

    protected function phpIdeHelper(): void
    {
        $filePath = static::phpIdeHelperPath();
        $contents = file_get_contents(__DIR__.'/../../_ide_helper.php');
        if ($contents === false) {
            throw new \Exception('Could not load the contents of _ide_helper.php. Try deleting /vendor and run composer install again.');
        }

        file_put_contents($filePath, $this->withGeneratedNotice($contents));

        $this->info("Wrote PHP definitions to $filePath.");
    }

    public static function phpIdeHelperPath(): string
    {
        return base_path().'/_lighthouse_ide_helper.php';
    }

    protected function withGeneratedNotice(string $phpContents): string
    {
        return substr_replace(
            $phpContents,
            self::OPENING_PHP_TAG.self::GENERATED_NOTICE,
            0,
            strlen(self::OPENING_PHP_TAG)
        );
    }
}
