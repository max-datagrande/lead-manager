import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
  return (
    <>
      <div className="flex aspect-square items-center justify-center rounded-md">
        <AppLogoIcon className="size-8 fill-current text-white dark:text-black" />
      </div>
      <div className="grid flex-1 text-left text-sm">
        <span className="mb-0.5 truncate leading-tight font-semibold">DataLeads</span>
      </div>
    </>
  );
}
