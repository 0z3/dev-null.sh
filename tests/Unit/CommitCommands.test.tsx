/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

type Issue = { number: number; url: string };
type SubmitOptions = {
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number }) => void;
    onNetworkError: (error: Error) => void;
};

let state: unknown[] = [];
let cursor = 0;
const request = {
    processing: false,
    transform: vi.fn(),
    submit: vi.fn<(route: unknown, options: SubmitOptions) => Promise<Issue>>(),
};
const toast = { error: vi.fn(), success: vi.fn() };

vi.doMock('react', () => ({
    ...React,
    useId: () => 'commands',
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = { current: initial };
        }
        return state[index];
    },
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }
        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function' ? value(state[index]) : value;
            },
        ];
    },
}));
vi.doMock('react-dom', async () => ({
    ...(await vi.importActual('react-dom')),
    createPortal: (children: ReactNode) => children,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    useHttp: () => request,
}));
vi.doMock('sonner', () => ({ toast }));

const { CommitCommands } =
    await import('../../resources/js/components/commit-commands');
const { store } =
    await import('../../resources/js/actions/App/Http/Controllers/IssueController');

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

const onProcessingChange = vi.fn();
const props = {
    body: 'The timeline jumps after posting',
    disabled: false,
    hasSecrets: false,
    onProcessingChange,
};

function render(overrides: Partial<typeof props> = {}) {
    cursor = 0;
    const tree = elements(CommitCommands({ ...props, ...overrides }));
    const input = tree.find((element) => element.props.role === 'combobox')!;
    const run = tree.find(
        (element) => element.props.type === 'button' && element.props.size,
    )!;
    const options = tree.filter((element) => element.props.role === 'option');
    return { tree, input, run, options };
}

function key(
    name: string,
    shiftKey = false,
    overrides: Partial<typeof props> = {},
) {
    const event = {
        key: name,
        shiftKey,
        nativeEvent: { isComposing: false },
        preventDefault: vi.fn(),
        stopPropagation: vi.fn(),
    };
    (render(overrides).input.props.onKeyDown as (event: unknown) => void)(
        event,
    );
    return event;
}

function type(value: string) {
    (render().input.props.onChange as (event: unknown) => void)({
        target: { value },
    });
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

beforeEach(() => {
    vi.clearAllMocks();
    state = [];
    vi.stubGlobal('document', { body: {} });
    request.submit.mockResolvedValue({
        number: 42,
        url: 'https://github.com/nunomaduro/dev-null.sh/issues/42',
    });
});

test('arrow keys cycle suggestions, Tab completes and Escape dismisses without leaving the composer', () => {
    type('/');
    let view = render();
    assert.equal(view.input.props['aria-expanded'], true);
    assert.deepEqual(
        view.options.map((option) => option.props['aria-selected']),
        [true, false],
    );

    assert.equal(key('ArrowDown').preventDefault.mock.calls.length, 1);
    assert.equal(
        render().input.props['aria-activedescendant'],
        'commands-option-1',
    );
    key('ArrowDown');
    assert.equal(
        render().input.props['aria-activedescendant'],
        'commands-option-0',
    );
    key('ArrowUp');
    view = render();
    assert.deepEqual(
        view.options.map((option) => option.props['aria-selected']),
        [false, true],
    );

    const tab = key('Tab');
    assert.equal(tab.preventDefault.mock.calls.length, 1);
    view = render();
    assert.equal(view.input.props.value, '/bug');
    assert.equal(view.input.props['aria-expanded'], false);
    assert.equal(view.run.props.disabled, false);

    type('/f');
    assert.equal(render().options.length, 1);
    const escape = key('Escape');
    assert.equal(escape.preventDefault.mock.calls.length, 1);
    assert.equal(escape.stopPropagation.mock.calls.length, 1);
    view = render();
    assert.equal(view.input.props['aria-expanded'], false);
    assert.equal(view.options.length, 0);
    assert.equal(view.input.props.value, '/f');

    const shiftTab = key('Tab', true);
    assert.equal(shiftTab.preventDefault.mock.calls.length, 0);
    assert.equal(request.submit.mock.calls.length, 0);
});

test('Enter on a partial command completes the suggestion instead of submitting anything', () => {
    type('/fe');
    const enter = key('Enter');
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.equal(enter.stopPropagation.mock.calls.length, 1);
    assert.equal(render().input.props.value, '/feature');
    assert.equal(request.submit.mock.calls.length, 0);
    assert.equal(onProcessingChange.mock.calls.length, 0);
});

test('Enter sends only the issue request, ignores repeats while running and keeps the draft', async () => {
    let resolve: (issue: Issue) => void = () => {};
    request.submit.mockReturnValue(
        new Promise((done) => {
            resolve = done;
        }),
    );
    type('/bug');

    const enter = key('Enter');
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.equal(enter.stopPropagation.mock.calls.length, 1);
    assert.deepEqual(onProcessingChange.mock.calls, [[true]]);
    assert.equal(request.submit.mock.calls.length, 1);
    assert.deepEqual(request.submit.mock.calls[0][0], store());
    const payload = request.transform.mock.calls[0][0] as () => unknown;
    assert.deepEqual(payload(), { command: '/bug', body: props.body });

    key('Enter');
    (render().run.props.onClick as () => void)();
    assert.equal(request.submit.mock.calls.length, 1);

    resolve({
        number: 42,
        url: 'https://github.com/nunomaduro/dev-null.sh/issues/42',
    });
    await settle();
    assert.deepEqual(onProcessingChange.mock.calls, [[true], [false]]);
    assert.equal(render().input.props.value, '');
    assert.equal(toast.success.mock.calls.length, 1);
    assert.match(
        toast.success.mock.calls[0][0] as string,
        /#42\. Your draft is still here/,
    );
    assert.equal(props.body, 'The timeline jumps after posting');
});

test('commands do not run while the commit form cannot submit or the draft has secrets', () => {
    type('/feature');
    assert.equal(render({ disabled: true }).run.props.disabled, true);
    const enter = key('Enter', false, { disabled: true });
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.equal(request.submit.mock.calls.length, 0);

    (render({ hasSecrets: true }).run.props.onClick as () => void)();
    assert.equal(request.submit.mock.calls.length, 0);
    assert.match(toast.error.mock.calls[0][0] as string, /secrets/);
    assert.equal(onProcessingChange.mock.calls.length, 0);
});

test('a failed request keeps the command for a retry', async () => {
    request.submit.mockImplementation((_route, options) => {
        options.onHttpException({ status: 502 });
        return Promise.reject(new Error('Bad gateway'));
    });
    type('/feature');
    key('Enter');
    await settle();

    assert.deepEqual(onProcessingChange.mock.calls, [[true], [false]]);
    assert.match(toast.error.mock.calls[0][0] as string, /draft is safe/);
    assert.equal(toast.success.mock.calls.length, 0);
    let view = render();
    assert.equal(view.input.props.value, '/feature');
    assert.equal(view.input.props.disabled, false);
    assert.equal(view.run.props.disabled, false);

    request.submit.mockImplementation((_route, options) => {
        options.onNetworkError(new Error('offline'));
        return Promise.reject(new Error('offline'));
    });
    key('Enter');
    await settle();
    assert.equal(request.submit.mock.calls.length, 2);
    assert.match(toast.error.mock.calls[1][0] as string, /Connection lost/);
    view = render();
    assert.equal(view.input.props.value, '/feature');
});
