<?php

namespace App\Controller;

use App\AI\Agent\CodeReviewAgent;
use App\AI\Review\ReviewFinding;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiTestController
{
    #[Route('/ai/test', name: 'ai_test')]
    public function test(CodeReviewAgent $agent): Response
    {
        $code = <<<'PHP'
<?php

function getUser(int $id): array
{
    $user = $database->query("SELECT * FROM users WHERE id = $id");

    return $user->fetch();

    echo "This line will never be executed.";
}
PHP;

        $review = $agent->review(__FILE__, $code);

        $output = [
            'findings_count' => $review->count(),
            'has_findings' => $review->hasFindings(),
            'findings' => array_map(
                static function (ReviewFinding $finding): array {
                    return [
                        'severity' => $finding->getSeverity(),
                        'category' => $finding->getCategory(),
                        'message' => $finding->getMessage(),
                        'suggestion' => $finding->getSuggestion(),
                    ];
                },
                $review->getFindings()
            ),
        ];

        return new Response(
            '<pre>' . htmlspecialchars(
                json_encode(
                    $output,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            ) . '</pre>'
        );
    }
}
