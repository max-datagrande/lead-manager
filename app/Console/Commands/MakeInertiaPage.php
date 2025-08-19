<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeInertiaPage extends Command
{
  /**
   * The name and signature of the console command.
   * Hacemos el argumento 'name' opcional para permitir prompts interactivos
   */
  protected $signature = 'make:inertia-page {name?} {--tsx} {--component} {--force}';

  /**
   * The console command description.
   */
  protected $description = 'Create a new Inertia React page with interactive prompts and folder support';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    // Get page name, with interactive prompt if not given
    $name = $this->getPageName();

    if (!$name) {
      $this->error('Page name is required!');
      return Command::FAILURE;
    }

    // Process name to handle folders and formatting
    $pathInfo = $this->processPageName($name);

    if (!$pathInfo) {
      $this->error('Invalid page name format!');
      return Command::FAILURE;
    }

    $useTsx = $this->option('tsx');
    $isComponent = $this->option('component');
    $force = $this->option('force');

    // If --tsx is not specified, ask interactively
    if (!$useTsx && !$this->option('no-interaction')) {
      $useTsx = $this->confirm('Do you want to create a TypeScript (.tsx) file?', false);
    }

    $extension = $useTsx ? 'tsx' : 'jsx';
    $directory = $isComponent ? 'components' : 'pages';

    // Build the complete path with nested folders
    $fullPath = $pathInfo['directory'] ? "{$pathInfo['directory']}/{$pathInfo['filename']}" : $pathInfo['filename'];

    $filePath = resource_path("js/{$directory}/{$fullPath}.{$extension}");

    // Check if the file already exists
    if (File::exists($filePath) && !$force) {
      if ($this->confirm("File {$fullPath}.{$extension} already exists in {$directory}/. Do you want to overwrite it?", false)) {
        $force = true;
      } else {
        $this->info('Operation cancelled.');
        return Command::SUCCESS;
      }
    }

    // Create the file
    $this->createFile($pathInfo['componentName'], $filePath, $useTsx, $isComponent, $pathInfo['title']);

    $this->info("✅ {$directory}/{$fullPath}.{$extension} created successfully!");
    $this->line("📁 Location: {$filePath}");

    // Show next steps
    $this->showNextSteps($pathInfo['componentName'], $isComponent, $fullPath);

    return Command::SUCCESS;
  }

  /**
   * Get page name with interactive prompt if needed
   */
  private function getPageName(): ?string
  {
    $name = $this->argument('name');

    // If the name is not provided as an argument, interactively request
    if (!$name && !$this->option('no-interaction')) {
      $name = $this->ask('What is the name of the page/component? (e.g., "app-sidebar", "Visitors/index", "Users/user-profile")');
    }

    return $name;
  }

  /**
   * Process page name to handle folders and formats
   */
  private function processPageName(string $name): ?array
  {
    // Split by "/" to manage folders
    $parts = explode('/', $name);
    $filename = array_pop($parts); // The last element is the file name
    $directory = implode('/', $parts); // The rest are folders

    // Validate the file name
    if (!$this->isValidFileName($filename)) {
      return null;
    }

    // Convert the file name to kebab-case format for the file
    $kebabFilename = Str::kebab($filename);

    // Convert the file name to PascalCase for the component name
    $componentName = Str::studly(str_replace('-', '_', $kebabFilename));

    // Create a readable title
    $title = Str::title(str_replace(['-', '_'], ' ', $kebabFilename));

    return [
      'directory' => $directory ?: null,
      'filename' => $kebabFilename,
      'componentName' => $componentName,
      'title' => $title,
    ];
  }

  /**
   * Validate that the file name is valid
   *
   * @param string $name
   * @return bool
   */
  private function isValidFileName(string $name): bool
  {
    // Allow letters, numbers, hyphens, and underscores
    return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name);
  }

  /**
   * Create the file with the appropriate content
   *
   * @param string $componentName
   * @param string $path
   * @param bool $useTsx
   * @param bool $isComponent
   * @param string $title
   * @return void
   */
  private function createFile(string $componentName, string $path, bool $useTsx, bool $isComponent, string $title): void
  {
    $stub = $this->getStub($useTsx, $isComponent);
    $content = str_replace(['{{componentName}}', '{{title}}'], [$componentName, $title], $stub);

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $content);
  }

  /**
   * Get the appropriate template based on options
   *
   * @param bool $useTsx
   * @param bool $isComponent
   * @return string
   */
  private function getStub(bool $useTsx = false, bool $isComponent = false): string
  {
    if ($isComponent) {
      return $this->getComponentStub($useTsx);
    }

    return $this->getPageStub($useTsx);
  }

  /**
   * Template for Inertia pages
   *
   * @param bool $useTsx
   * @return string
   */
  private function getPageStub(bool $useTsx): string
  {
    if ($useTsx) {
      return "import React from 'react';
        import { Head } from '@inertiajs/react';

        interface Props {
          // Define your props here
        }

        /**
         * {{title}} Page Component
         *
         * @description Página principal para {{title}}
         */
        export default function {{componentName}}({}: Props) {
          return (
            <>
              <Head title=\"{{title}}\" />
              <div className=\"container mx-auto px-4 py-8\">
                <h1 className=\"text-3xl font-bold text-gray-900 mb-6\">
                  {{title}}
                </h1>
                <div>
                  {/* Contenido de la página */}
                  <p className=\"text-gray-600\">Welcome to the {{title}} page!</p>
                </div>
              </div>
            </>
          );
        }";
    }

    return "import React from 'react';
      import { Head } from '@inertiajs/react';

      /**
       * {{title}} Page Component
       *
       * @description Página principal para {{title}}
       */
      export default function {{componentName}}() {
        return (
          <>
            <Head title=\"{{title}}\" />
            <div className=\"container mx-auto px-4 py-8\">
              <h1 className=\"text-3xl font-bold text-gray-900 mb-6\">
                {{title}}
              </h1>
              <div>
                {/* Contenido de la página */}
                <p className=\"text-gray-600\">Welcome to the {{title}} page!</p>
              </div>
            </div>
          </>
        );
      }";
  }

  /**
   * Template for reusable components
   */
  private function getComponentStub(bool $useTsx): string
  {
    if ($useTsx) {
      return "import React from 'react';
        interface {{componentName}}Props {
          // Define your props here
          className?: string;
        }

        /**
         * {{componentName}} Component
         *
         * @description Reusable component {{componentName}}
         */
        export default function {{componentName}}({ className = '' }: {{componentName}}Props) {
          return (
            <div className={`{{componentName.toLowerCase()}} \${className}`}>
                {/* Component content */}
                <p>{{componentName}} component</p>
            </div>
          );
        }";
    }

    return "import React from 'react';
      /**
       * {{componentName}} Component
       *
       * @description Reusable component {{componentName}}
       */
      export default function {{componentName}}({ className = '' }) {
        return (
          <div className={`{{componentName.toLowerCase()}} \${className}`}>
            {/* Component content */}
            <p>{{componentName}} component</p>
          </div>
        );
      }";
  }

  /**
   * Show next steps to the user
   */
  private function showNextSteps(string $componentName, bool $isComponent, string $fullPath): void
  {
    $this->newLine();
    $this->line('<fg=yellow>📋 Next steps:</>');

    if ($isComponent) {
      $this->line("   • Import the component: import {$componentName} from '@/components/{$fullPath}'");
      $this->line("   • Use it in your pages: <{$componentName} />");
    } else {
      $this->line('   • Add a route in routes/web.php');
      $this->line("   • Create a controller method to return Inertia::render('{$fullPath}')");
      $this->line('   • Access your page at the defined route');
    }

    $this->line('   • Customize the component according to your needs');
  }
}
