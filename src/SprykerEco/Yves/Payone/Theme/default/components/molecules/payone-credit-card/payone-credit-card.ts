/* tslint:disable: no-any */
/* tslint:disable: max-file-line-count */
declare var Payone: any;

import Component from 'ShopUi/models/component';
import ScriptLoader from 'ShopUi/components/molecules/script-loader/script-loader';

const CURRENT_PAYMENT_METHOD = 'payoneCreditCard';
const CHECK_CALLBACK_ID = 'checkCallback';
const CHECK_CALLBACK_VALID_RESPONSE_STATUS = 'VALID';

// configuration for Hosted Iframe.
// https://github.com/fjbender/simple-php-integration#build-the-form
const defaultHostedIFramesConfig = {
    fields: {
        cardtype: {
            selector: 'cardtype',
            cardtypes: ['V', 'M'],
        },
        cardpan: {
            selector: 'cardpan',
            type: 'text',
        },
        cardcvc2: {
            selector: 'cardcvc2',
            type: 'password',
            size: '3',
            maxlength: '3',
            length: { V: 3, M: 3 },
        },
        cardexpiremonth: {
            selector: 'cardexpiremonth',
            type: 'select',
            size: '2',
            maxlength: '2',
            iframe: {
                width: '100%',
            },
        },
        cardexpireyear: {
            selector: 'cardexpireyear',
            type: 'select',
            iframe: {
                width: '100%',
            },
        },
    },

    defaultStyle: {
        input: `font-size: 0.875em; height: 2rem; width: 100%; border: 0; outline: 1px solid #dadada; outline-offset: -1px;`,
        select: `font-size: 0.875em; height: 2rem; width: 100%; border: 0; outline: 1px solid #dadada; outline-offset: -1px; background-color: #fefefe;`,
        iframe: {
            height: '35px',
            width: '100%',
        },
    },

    error: 'errorOutput',
};

export default class PayoneCreditCard extends Component {
    scriptLoader: ScriptLoader;
    form: HTMLFormElement;
    hostedIFramesApi: any;
    cardTypeInput: HTMLInputElement;
    cardHolderInput: HTMLInputElement;
    clientApiConfigInput: HTMLInputElement;
    languageInput: HTMLInputElement;
    pseudoCardPanInput: HTMLInputElement;
    errorElement: HTMLElement;
    protected isPaymentValid: boolean = false;

    protected submitButton: HTMLButtonElement[];

    protected readyCallback(): void {}

    protected init(): void {
        this.scriptLoader = <ScriptLoader>this.querySelector('script-loader');
        this.form = <HTMLFormElement>document.querySelector(this.formSelector);
        this.cardHolderInput = <HTMLInputElement>this.querySelector(this.cardHolderSelector);
        this.clientApiConfigInput = <HTMLInputElement>this.querySelector(this.clientApiConfigSelector);
        this.languageInput = <HTMLInputElement>this.querySelector(this.languageSelector);
        this.pseudoCardPanInput = <HTMLInputElement>this.querySelector(this.pseudoCardPanSelector);
        this.errorElement = this.querySelector(this.errorContainer);
        this.submitButton = <HTMLButtonElement[]>Array.from(
            document.getElementsByClassName(`${this.jsName}__submit`)
        );

        this.mapEvents();
    }

    protected mapEvents(): void {
        this.scriptLoader.addEventListener('scriptload', (event: Event) => this.onScriptLoad(event));
        this.form.addEventListener('submit', (event: Event) => this.onSubmit(event));
    }

    protected onScriptLoad(event: Event): void {
        this.addCheckCallbackToGlobalScope();
        this.loadPayoneIFrame();
    }

    protected onSubmit(event: Event): void {
        if (!this.isCurrentPaymentMethod) {
            return;
        }

        event.preventDefault();

        if (this.isPaymentValid) {
            this.form.submit();
        }

        this.checkCreditCard();
    }

    protected addCheckCallbackToGlobalScope(): void {
        window[CHECK_CALLBACK_ID] = this.checkCallback.bind(this);
    }

    protected async checkCallback(response: any): Promise<void> {
        if (response.status !== CHECK_CALLBACK_VALID_RESPONSE_STATUS) {
            setTimeout(() => this.enableSubmit(), 0);

            return;
        }

        this.pseudoCardPanInput.value = await Promise.resolve(response.pseudocardpan);
        this.setPaymentToValid();
        this.form.submit();
    }

    protected checkCreditCard(): void {
        if (this.hostedIFramesApi.isComplete() && this.cardHolderInput.value) {
            this.hostedIFramesApi.creditCardCheck(CHECK_CALLBACK_ID);

            return;
        }

        this.errorElement.innerHTML = this.hostedIFramesConfig.language.transactionRejected;
        setTimeout(() => this.enableSubmit(), 0);
    }

    protected enableSubmit(): void {
        if (this.submitButton.length) {
            this.submitButton.forEach(button => {
                button.removeAttribute('disabled');
            });

            return;
        }

        const buttons = <HTMLButtonElement[]>Array.from(this.form.getElementsByTagName('button'));
        buttons.forEach(button => {
            button.removeAttribute('disabled');
        });
    }

    protected loadPayoneIFrame(): void {
        this.hostedIFramesApi = new Payone.ClientApi.HostedIFrames(this.hostedIFramesConfig, this.clientApiConfig);

        Payone.ClientApi.Language.de.placeholders.cardpan = '_ _ _ _  _ _ _ _  _ _ _ _  _ _ _ _';
        Payone.ClientApi.Language.de.placeholders.cvc = '• • •';
    }

    protected setPaymentToValid(): void {
        this.isPaymentValid = true;
    }

    get isCurrentPaymentMethod(): boolean | null {
        const currentPaymentMethodInput = <HTMLInputElement>document.querySelector(this.currentPaymentMethodSelector);

        return currentPaymentMethodInput?.value
            ? currentPaymentMethodInput.value === CURRENT_PAYMENT_METHOD
            : null;
    }

    get language(): string {
        const languageCodeLenght = 2;
        const languageCode = !!this.languageInput.value ? this.languageInput.value.substr(0, languageCodeLenght) : 'de';

        return Payone.ClientApi.Language[languageCode] || Payone.ClientApi.Language.de;
    }

    get hostedIFramesConfig(): any {
        return {
            ...defaultHostedIFramesConfig,
            language: this.language
        };
    }

    get clientApiConfig(): any {
        return JSON.parse(this.clientApiConfigInput.value);
    }

    get formSelector(): string {
        return this.getAttribute('form-selector');
    }

    get clientApiConfigSelector(): string {
        return this.getAttribute('client-api-config-selector');
    }

    get languageSelector(): string {
        return this.getAttribute('language-selector');
    }

    get cardTypeSelector(): string {
        return this.getAttribute('card-type-selector');
    }

    get cardHolderSelector(): string {
        return this.getAttribute('card-holder-selector');
    }

    get pseudoCardPanSelector(): string {
        return this.getAttribute('pseudo-card-pan-selector');
    }

    get errorContainer(): string {
        return this.getAttribute('error-container-selector');
    }

    get currentPaymentMethodSelector(): string {
        return this.getAttribute('current-payment-method-selector');
    }
}
