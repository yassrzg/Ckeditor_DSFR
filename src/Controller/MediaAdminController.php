<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Controller;

use Yassrzg\CkeditorDsfrBundle\Editor\DsfrArtwork;
use Yassrzg\CkeditorDsfrBundle\Entity\Media;
use Yassrzg\CkeditorDsfrBundle\Enum\MediaType;
use Yassrzg\CkeditorDsfrBundle\Form\MediaFormType;
use Yassrzg\CkeditorDsfrBundle\Media\MediaException;
use Yassrzg\CkeditorDsfrBundle\Media\MediaManager;
use Yassrzg\CkeditorDsfrBundle\Repository\MediaRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

/**
 * Médiathèque (ckeditor_dsfr.roles.admin) : images et documents utilisables dans l'éditeur, et données de l'éditeur
 * (médias, icônes et pictogrammes du DSFR, ajout rapide). Routes : config/routes/admin.php.
 */
final class MediaAdminController extends AbstractEditorController
{
    private const int PER_PAGE = 24;

    public function __construct(
        private readonly MediaManager $mediaManager,
        private readonly MediaRepository $mediaRepository,
        private readonly LoggerInterface $logger,
        private readonly DsfrArtwork $artwork,
    ) {
    }

    public function index(
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
    ): Response {
        $this->denyUnless('admin');
        $mediaType = MediaType::tryFrom((string) $type);
        $q = mb_substr(trim($q), 0, 100);
        $total = $this->mediaRepository->countSearch($mediaType, $q);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return $this->render('@CkeditorDsfr/admin/media/index.html.twig', [
            'medias' => $this->mediaRepository->search($mediaType, $q, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'counts' => $this->mediaRepository->countByType(),
            'types' => MediaType::cases(),
            'type' => $mediaType,
            'q' => $q,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function create(Request $request, string $type): Response
    {
        return $this->edit($request, new Media(MediaType::from($type)));
    }

    public function edit(Request $request, Media $media): Response
    {
        $this->denyUnless('admin');
        $form = $this->createForm(MediaFormType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $isNew = null === $media->getId();
                $this->mediaManager->save($media, $form->get('file')->getData());
                $this->success($isNew ? 'Le média a été ajouté.' : 'Le média a été modifié.');

                return $this->redirectToRoute('ckeditor_dsfr_media_index', ['type' => $media->getType()->value]);
            } catch (MediaException $e) {
                $form->addError(new FormError($e->getMessage()));
            } catch (\RuntimeException $e) {
                $this->logger->error('Enregistrement d\'un média impossible : {error}', ['error' => $e->getMessage(), 'exception' => $e]);
                $form->addError(new FormError('Le fichier n\'a pas pu être enregistré. Réessayez.'));
            }
        }

        return $this->renderForm('@CkeditorDsfr/admin/media/form.html.twig', $form, ['media' => $media, 'usages' => $this->mediaManager->usages($media)]);
    }

    public function delete(Request $request, Media $media): Response
    {
        $this->denyUnless('admin');
        if (!$this->isCsrfTokenValid('ckeditor-dsfr-delete-media-'.$media->getId(), (string) $request->getPayload()->get('_token'))) {
            $this->error('La demande a expiré : rechargez la page et recommencez.');

            return $this->redirectToRoute('ckeditor_dsfr_media_edit', ['id' => $media->getId()]);
        }

        $type = $media->getType()->value;
        try {
            $this->mediaManager->delete($media);
            $this->success('Le média a été supprimé.');
        } catch (MediaException $e) {
            $this->error($e->getMessage());

            return $this->redirectToRoute('ckeditor_dsfr_media_edit', ['id' => $media->getId()]);
        }

        return $this->redirectToRoute('ckeditor_dsfr_media_index', ['type' => $type]);
    }

    /**
     * Médias d'une catégorie pour l'éditeur (choix d'une image ou d'un document).
     */
    public function selection(string $type): JsonResponse
    {
        $this->denyUnless('admin');

        return $this->json(array_map($this->selectionItem(...), $this->mediaRepository->search(MediaType::from($type))));
    }

    /**
     * Icônes et pictogrammes du DSFR proposés dans l'éditeur, au même format que les médias ; liste fixe, mise en
     * cache par le navigateur.
     */
    public function artwork(): JsonResponse
    {
        $this->denyUnless('admin');
        $artwork = $this->artwork;
        $icons = [];
        foreach ($artwork->icons() as $class => $category) {
            $icons[] = ['id' => $class, 'title' => substr($class, \strlen('fr-icon-')), 'detail' => $category];
        }
        $pictograms = array_map(static fn (string $p): array => [
            'id' => $p,
            'title' => basename($p),
            'detail' => \dirname($p),
            'url' => $artwork->pictogramUrl($p),
        ], $artwork->pictograms());

        $response = $this->json(['icon' => $icons, 'pictogram' => $pictograms]);
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }

    /**
     * Ajout depuis l'éditeur (étape « Médiathèque » de la fenêtre des composants) : GET rend les champs du formulaire
     * (fragment, sans balise form : la fenêtre est dans le formulaire de la page) ; POST valide et enregistre, puis
     * répond le média en JSON (201), ou les champs avec leurs erreurs (422).
     */
    public function quickAdd(Request $request, string $type): Response
    {
        $this->denyUnless('admin');
        $media = new Media(MediaType::from($type));
        $form = $this->createForm(MediaFormType::class, $media);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->mediaManager->save($media, $form->get('file')->getData());

                return $this->json($this->selectionItem($media), Response::HTTP_CREATED);
            } catch (MediaException|\RuntimeException $e) {
                $this->logger->error('Ajout à la médiathèque depuis l\'éditeur impossible : {error}', ['error' => $e->getMessage(), 'exception' => $e]);
                $form->addError(new FormError($e instanceof MediaException ? $e->getMessage() : 'Le fichier n\'a pas pu être enregistré. Réessayez.'));
            }
        }

        return $this->render('@CkeditorDsfr/admin/media/_quick_form.html.twig', ['form' => $form->createView(), 'media' => $media],
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return array{id: ?int, title: string, detail: string, url: string}
     */
    private function selectionItem(Media $media): array
    {
        return [
            'id' => $media->getId(),
            'title' => $media->getTitle(),
            'detail' => $media->getDetail(),
            'format' => $media->getFormat(),
            'url' => $this->generateUrl('ckeditor_dsfr_media_show', ['id' => $media->getId()]),
        ];
    }
}
