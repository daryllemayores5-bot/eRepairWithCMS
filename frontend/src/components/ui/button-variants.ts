import { cva } from 'class-variance-authority';
export const buttonVariants = cva('inline-flex max-w-full shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-normal rounded-xl text-center text-sm font-semibold leading-5 transition-[background-color,box-shadow,transform] active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none [&_svg]:size-4 [&_svg]:shrink-0', {
  variants: {
    variant: {
      default: 'border border-primary bg-primary text-primary-foreground shadow-sm shadow-blue-900/10 hover:bg-blue-700 hover:shadow-md',
      outline: 'border border-slate-300 bg-white text-slate-700 shadow-xs hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700',
      ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
      destructive: 'border border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100'
    },
    size: {
      default: 'min-h-11 px-5 py-2.5',
      sm: 'min-h-11 px-3 py-2',
      lg: 'min-h-12 px-6 py-3',
      icon: 'size-11'
    }
  },
  defaultVariants: {
    variant: 'default',
    size: 'default'
  }
});
