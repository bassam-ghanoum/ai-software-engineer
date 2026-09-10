<?php

namespace App\Controller;

use App\AI\Agent\CodeReviewAgent;
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

        $review = $agent->review($code);

        return new Response(
            '<pre>' . htmlspecialchars($review) . '</pre>'
        );
    }
}