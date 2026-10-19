<?php

namespace App\Services;

class KeywordScorer
{
    protected array $aliases = [
        'js' => 'javascript',
        'es6' => 'javascript',
        'ecmascript' => 'javascript',

        'ts' => 'typescript',

        'reactjs' => 'react',
        'react.js' => 'react',
        'react js' => 'react',
        'nextjs' => 'next.js',
        'next js' => 'next.js',

        'nodejs' => 'node.js',
        'node' => 'node.js',
        'node js' => 'node.js',
        'expressjs' => 'express.js',
        'express js' => 'express.js',

        'vuejs' => 'vue',
        'vue.js' => 'vue',
        'vue js' => 'vue',
        'nuxtjs' => 'nuxt.js',
        'nuxt js' => 'nuxt.js',

        'angularjs' => 'angular.js',
        'angular js' => 'angular.js',

        'php language' => 'php',
        'php programming' => 'php',
        'php programming language' => 'php',

        'laravel framework' => 'laravel',
        'laravel php' => 'laravel',

        'python3' => 'python',
        'python 3' => 'python',
        'py' => 'python',

        'java language' => 'java',
        'core java' => 'java',

        'c sharp' => 'c#',
        'c-sharp' => 'c#',
        'csharp' => 'c#',
        'dotnet' => '.net',
        'dot net' => '.net',
        'asp.net core' => 'asp.net',

        'cpp' => 'c++',
        'c plus plus' => 'c++',

        'golang' => 'go',
        'go lang' => 'go',

        'ruby on rails' => 'rails',
        'ror' => 'rails',

        'postgres' => 'postgresql',
        'psql' => 'postgresql',
        'mongo' => 'mongodb',
        'mongo db' => 'mongodb',
        'maria db' => 'mariadb',
        'ms sql' => 'sql server',
        'mssql' => 'sql server',
        'microsoft sql server' => 'sql server',

        'tailwind' => 'tailwind css',
        'tailwindcss' => 'tailwind css',
        'bootstrap css' => 'bootstrap',
        'sass' => 'sass',
        'scss' => 'sass',
        'less css' => 'less',

        'restful api' => 'rest api',
        'restful apis' => 'rest api',
        'rest apis' => 'rest api',
        'graphql api' => 'graphql',
        'grpc' => 'gRPC',

        'amazon web services' => 'aws',
        'google cloud platform' => 'gcp',
        'microsoft azure' => 'azure',
        'k8s' => 'kubernetes',
        'gh actions' => 'github actions',
        'ci cd' => 'ci/cd',
        'cicd' => 'ci/cd',
        'continuous integration' => 'ci/cd',
        'continuous deployment' => 'ci/cd',

        'git hub' => 'github',
        'gitlab ci' => 'gitlab',
        'bit bucket' => 'bitbucket',

        'react native' => 'react native',
        'flutter framework' => 'flutter',
        'dart language' => 'dart',
        'ios development' => 'ios',
        'android development' => 'android',

        'qa' => 'quality assurance',
        'sqa' => 'software quality assurance',
        'e2e' => 'end-to-end testing',
        'unit test' => 'unit testing',
        'automated test' => 'automated testing',

        'ml' => 'machine learning',
        'ai' => 'artificial intelligence',
        'artificial intelligence' => 'artificial intelligence',
        'natural language processing' => 'nlp',
        'scikit learn' => 'scikit-learn',
        'sklearn' => 'scikit-learn',
        'tf' => 'tensorflow',
        'pytorch framework' => 'pytorch',
        'gen ai' => 'generative ai',
        'genai' => 'generative ai',

        'vs code' => 'visual studio code',
        'vscode' => 'visual studio code',
        'amazon s3' => 's3',
        'elastic search' => 'elasticsearch',
    ];

    public function calculate(array $requiredSkills, string $resumeText): array
    {
        if (empty($requiredSkills) || empty(trim($resumeText))) {
            return [
                'score' => 0,
                'matched_skills' => [],
            ];
        }

        $normalizedResume = strtolower($resumeText);
        $matchedSkills = [];

        foreach ($requiredSkills as $skill) {
            $normalizedSkill = $this->normalizeSkill($skill);

            // Regex Word Boundary Matching with Escape
            $escapedSkill = preg_quote($normalizedSkill, '/');
            $pattern = '/\b' . $escapedSkill . '\b/i';

            if (preg_match($pattern, $normalizedResume)) {
                $matchedSkills[] = $skill;
            }
        }

        $totalSkills = count($requiredSkills);
        $score = $totalSkills > 0
            ? (int) round((count($matchedSkills) / $totalSkills) * 100)
            : 0;

        return [
            'score' => $score,
            'matched_skills' => array_values(array_unique($matchedSkills)),
        ];
    }

    protected function normalizeSkill(string $skill): string
    {
        $cleanSkill = strtolower(trim($skill));
        return $this->aliases[$cleanSkill] ?? $cleanSkill;
    }
}
