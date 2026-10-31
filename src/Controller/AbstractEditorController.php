<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base des contrôleurs du bundle : droits configurables (ckeditor_dsfr.roles) et messages affichés par le gabarit
 * @CkeditorDsfr/admin/_layout.html.twig (types ckeditor_dsfr_success et ckeditor_dsfr_error).
 */
abstract class AbstractEditorController extends AbstractController
{
    /** @var array{admin: string, settings: string, view: string} */
    private array $roles;

    /**
     * @param array{admin: string, settings: string, view: string} $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @param 'admin'|'settings'|'view' $access
     */
    protected function denyUnless(string $access): void
    {
        $this->denyAccessUnlessGranted($this->roles[$access]);
    }

    protected function success(string $message): void
    {
        $this->addFlash('ckeditor_dsfr_success', $message);
    }

    protected function error(string $message): void
    {
        $this->addFlash('ckeditor_dsfr_error', $message);
    }

    /**
     * Page de formulaire : code 422 si elle est réaffichée après un envoi (formulaire invalide ou enregistrement
     * refusé), comme l'attend Turbo.
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $parameters
     */
    protected function renderForm(string $view, FormInterface $form, array $parameters = []): Response
    {
        return $this->render($view, ['form' => $form, ...$parameters], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
