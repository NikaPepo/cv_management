import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from 'react'

export type Locale = 'en' | 'ka'
export type Theme = 'light' | 'dark'

interface PreferencesState {
    locale: Locale
    theme: Theme
    setLocale: (locale: Locale) => void
    setTheme: (theme: Theme) => void
}

const STORAGE_KEY = 'cv-management.preferences.v1'

interface Stored {
    locale: Locale
    theme: Theme
}

const AppPreferencesContext = createContext<PreferencesState | null>(null)

function readStored(): Stored {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (raw === null) return { locale: 'en', theme: 'light' }
        const parsed = JSON.parse(raw) as Partial<Stored>
        return {
            locale: parsed.locale === 'ka' ? 'ka' : 'en',
            theme: parsed.theme === 'dark' ? 'dark' : 'light',
        }
    } catch {
        return { locale: 'en', theme: 'light' }
    }
}

const TRANSLATIONS: Record<Locale, Record<string, string>> = {
    en: {
        'app.title': 'CV Management',
        'nav.home': 'Home',
        'nav.positions': 'Positions',
        'nav.attributes': 'Attribute Library',
        'nav.profile': 'Profile',
        'nav.admin': 'Admin',
        'nav.login': 'Sign in',
        'nav.logout': 'Sign out',
        'nav.register': 'Sign up',
        'search.placeholder': 'Search…',
        'attr.library.title': 'Attribute Library',
        'attr.library.create': 'New attribute',
        'attr.library.category': 'Category',
        'attr.library.name': 'Name',
        'attr.library.type': 'Type',
        'attr.library.required': 'Required',
        'attr.library.options': 'Options (for One-of-many)',
        'attr.library.description': 'Description',
        'attr.library.delete': 'Delete',
        'attr.library.empty': 'No attributes yet.',
        'attr.library.addOption': 'Add option',
        'attr.lookup.prefix': 'Find by name…',
        'attr.lookup.category': 'Category',
        'attr.lookup.recent': 'Recently used',
        'profile.me.title': 'About me',
        'profile.info.title': 'Information',
        'profile.projects.title': 'Projects',
        'profile.cvs.title': 'CVs',
        'profile.save': 'Save',
        'profile.saved': 'Saved.',
        'profile.conflict': 'This data was changed in another session. Reload to see the latest values.',
        'attr.empty': 'Empty',
        'theme.light': 'Light',
        'theme.dark': 'Dark',
        'common.cancel': 'Cancel',
        'common.save': 'Save',
        'common.create': 'Create',
        'common.delete': 'Delete',
        'common.edit': 'Edit',
        'common.add': 'Add',
        'common.remove': 'Remove',
        'account_type.choose': 'Choose your account type',
        'account_type.candidate': 'Candidate',
        'account_type.recruiter': 'Recruiter',
        'account_type.error.save': 'Could not save account type.',
    },
    ka: {
        'app.title': 'CV მართვის სისტემა',
        'nav.home': 'მთავარი',
        'nav.positions': 'პოზიციები',
        'nav.attributes': 'ატრიბუტების ბიბლიოთეკა',
        'nav.profile': 'პროფილი',
        'nav.admin': 'ადმინი',
        'nav.login': 'შესვლა',
        'nav.logout': 'გასვლა',
        'nav.register': 'რეგისტრაცია',
        'search.placeholder': 'ძიება…',
        'attr.library.title': 'ატრიბუტების ბიბლიოთეკა',
        'attr.library.create': 'ახალი ატრიბუტი',
        'attr.library.category': 'კატეგორია',
        'attr.library.name': 'სახელი',
        'attr.library.type': 'ტიპი',
        'attr.library.required': 'სავალდებულო',
        'attr.library.options': 'ვარიანათები (One-of-many-სთვის)',
        'attr.library.description': 'აღწერა',
        'attr.library.delete': 'წაშლა',
        'attr.library.empty': 'ატრიბუტები ჯერ არ არის.',
        'attr.library.addOption': 'დამატება',
        'attr.lookup.prefix': 'ძებნა სახელით…',
        'attr.lookup.category': 'კატეგორია',
        'attr.lookup.recent': 'ბოლო დროს გამოყენებული',
        'profile.me.title': 'ჩემს შესახებ',
        'profile.info.title': 'ინფორმაცია',
        'profile.projects.title': 'პროექტები',
        'profile.cvs.title': 'CV-ები',
        'profile.save': 'შენახვა',
        'profile.saved': 'შენახულია.',
        'profile.conflict': 'ეს მონაცემები სხვა სესიაში შეიცვალა. განაახლეთ გვერდი უახლესი მნიშვნელობების სანახავად.',
        'attr.empty': 'ცარიელია',
        'theme.light': 'ღია',
        'theme.dark': 'მუქი',
        'common.cancel': 'გაუქმება',
        'common.save': 'შენახვა',
        'common.create': 'შექმნა',
        'common.delete': 'წაშლა',
        'common.edit': 'რედაქტირება',
        'common.add': 'დამატება',
        'common.remove': 'წაშლა',
        'account_type.choose': 'აირჩიეთ ანგარიშების ტიპი',
        'account_type.candidate': 'კანდიდატი',
        'account_type.recruiter': 'რეკრუტერი',
        'account_type.error.save': 'ანგარიშების ტიპი ვერ შევინახე.',
    },
}

export function AppPreferencesProvider({ children }: { children: ReactNode }) {
    const [prefs, setPrefs] = useState<Stored>(() => readStored())

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs))
        document.documentElement.dataset.bsTheme = prefs.theme
    }, [prefs])

    const setLocale = (locale: Locale) => setPrefs((p) => ({ ...p, locale }))
    const setTheme = (theme: Theme) => setPrefs((p) => ({ ...p, theme }))

    const t = (key: string): string =>
        TRANSLATIONS[prefs.locale][key] ?? TRANSLATIONS.en[key] ?? key

    return (
        <AppPreferencesContext.Provider
            value={{ ...prefs, setLocale, setTheme }}
        >
            <I18nContext.Provider value={t}>{children}</I18nContext.Provider>
        </AppPreferencesContext.Provider>
    )
}

const I18nContext = createContext<(key: string) => string>(() => '')

export function useTranslation(): (key: string) => string {
    return useContext(I18nContext)
}

export function usePreferences(): PreferencesState {
    const ctx = useContext(AppPreferencesContext)
    if (ctx === null) throw new Error('usePreferences must be inside provider')
    return ctx
}