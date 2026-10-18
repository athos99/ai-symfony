<?php

namespace App\Controller;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    public function __construct(#[Target('default')] private AgentInterface $agent) {}


    private function analyseMetadata(array $metadata): array
    {
        $info = [];
        foreach ($metadata as $key => $value) {

            if ($value instanceof FinishReason) {
                $info['finish_reason'] = $value->getRaw();
            } elseif ($value instanceof TokenUsage) {
                $info['promptTokens'] = $value->getPromptTokens();
                $info['completionTokens'] = $value->getCompletionTokens();
                $info['thinkingTokens'] = $value->getThinkingTokens();
                $info['toolTokens'] = $value->getToolTokens();
                $info['cachedTokens'] = $value->getCachedTokens();
                $info['cacheCreationTokens'] = $value->getCacheCreationTokens();
                $info['cacheReadTokens'] = $value->getCacheReadTokens();
                $info['remainingTokens'] = $value->getRemainingTokens();
                $info['remainingTokensMinute'] = $value->getRemainingTokensMinute();
                $info['remainingTokensMonth'] = $value->getRemainingTokensMonth();
                $info['totalTokens'] = $value->getTotalTokens();
            }
        }


        return $info;
    }

    #[Route('/chat', name: 'chat')]
    public function index(Request $request): Response
    {
        $response = null;
        $prompt = '';
        $info = null;
        if ($request->isMethod('POST')) {
            $prompt = (string) $request->request->get('prompt', '');
            if ($prompt !== '') {
                $execution = $this->agent->call($prompt);
                $response = $execution->getContent();
                $metadata = $execution->getMetadata()->all();
                $info = $this->analyseMetadata($metadata);
            }
        }




        return $this->render('chat.html.twig', [
            'prompt' => $prompt,
            'response' => $response,
            'info' => $info,
        ]);
    }
}
