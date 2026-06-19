<?php

namespace AnourValar\EloquentFile\Tests\Support;

use AnourValar\EloquentFile\Traits\ControllerTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Exposes \AnourValar\EloquentFile\Traits\ControllerTrait through HTTP routes.
 */
class TestController extends Controller
{
    use ControllerTrait;

    /**
     * Stubbed remote content for downloadFileFrom() (avoids real network calls).
     *
     * @var string|false
     */
    public static string|false $remoteContent = false;

    /**
     * Web-service: Upload a file.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function upload(Request $request): array
    {
        $fileVirtual = \DB::transaction(fn () => $this->uploadFileFrom($request));

        return ['file_virtual' => ['id' => $fileVirtual->id]];
    }

    /**
     * Web-service: Upload a file via a remote url.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function uploadFromUrl(Request $request): array
    {
        $closure = $this->downloadFileFrom(
            (string) $request->input('url'),
            [
                'entity' => $request->input('entity'),
                'entity_id' => $request->input('entity_id'),
                'name' => $request->input('name'),
            ]
        );

        $fileVirtual = \DB::transaction($closure);

        return ['file_virtual' => ['id' => $fileVirtual->id]];
    }

    /**
     * Web-service: Delete a file.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $fileVirtual = \DB::transaction(fn () => $this->deleteFileFrom($request));

        return ['file_virtual' => ['id' => $fileVirtual->id]];
    }

    /**
     * Web-service: Download a file (signed url).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadSigned(Request $request)
    {
        return $this->proxyUrlSigned($request, (bool) $request->input('as_download'));
    }

    /**
     * Web-service: Download a file (user authorization).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAuth(Request $request)
    {
        return $this->proxyUserAuthorize($request, (bool) $request->input('as_download'));
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Traits\ControllerTrait::downloadProcedure()
     */
    protected function downloadProcedure(string $url): string|false
    {
        return static::$remoteContent;
    }
}
