<?php

namespace App\Controller;

use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    public function __construct(#[Target('default')] private AgentInterface $agent)
    {
    }

    #[Route('/chat', name: 'chat')]
    public function index(Request $request): Response
    {
        $response = null;
        $prompt = '';
        if ($request->isMethod('POST')) {
            $prompt = (string) $request->request->get('prompt', '');
            if ($prompt !== '') {
                $execution = $this->agent->call($prompt);
                $response = $execution->getContent();
            }
        }

        return $this->render('chat/index.html.twig', [
            'prompt' => $prompt,
            'response' => $response,
        ]);
    }
}
