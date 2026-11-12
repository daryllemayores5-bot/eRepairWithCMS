import { nextTick, reactive } from 'vue';
import type { ZodType } from 'zod';
export type FormError = {
  message: string;
};
export type FormFields = {
  values: Record<string, string>;
  formState: {
    errors: Record<string, FormError | undefined>;
    isSubmitting: boolean;
  };
};

/** Retain the existing Zod schemas, step validation and error messages. */
export function useValidatedForm<T extends Record<string, string>>(schema: ZodType<T>, defaults: T) {
  const values = reactive({
    ...defaults
  }) as T;
  const formState = reactive({
    errors: {} as Record<string, FormError | undefined>,
    isSubmitting: false
  });
  function setError(name: string, error: FormError) {
    formState.errors[name] = error;
  }
  function validate(fields?: readonly string[]) {
    const result = schema.safeParse(values);
    const names = fields ?? Object.keys(values);
    for (const name of names) delete formState.errors[name];
    if (!result.success) {
      for (const issue of result.error.issues) {
        const name = String(issue.path[0]);
        if (names.includes(name) && !formState.errors[name]) setError(name, {
          message: issue.message
        });
      }
    }
    return { result, valid: names.every(name => !formState.errors[name]) };
  }
  function trigger(fields?: readonly string[]) {
    return validate(fields).valid;
  }
  function handleSubmit(submit: (values: T) => Promise<unknown>) {
    return async (event: Event) => {
      event.preventDefault();
      if (formState.isSubmitting) return;
      const element = event.currentTarget as HTMLFormElement | null;
      const { result } = validate();
      if (!result.success) {
        // Render the error descriptions before focus so assistive technology can read them.
        await nextTick();
        const invalid = Array.from(element?.elements ?? []).find(control =>
          (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)
          && formState.errors[control.name] && !control.matches(':disabled') && control.type !== 'hidden'
        ) as HTMLElement | undefined;
        invalid?.focus();
        return;
      }
      formState.isSubmitting = true;
      try {
        await submit({
          ...result.data
        });
      } finally {
        formState.isSubmitting = false;
      }
    };
  }
  return {
    values,
    formState,
    setError,
    trigger,
    handleSubmit
  };
}
