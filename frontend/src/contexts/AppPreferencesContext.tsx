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

// Locale -> BCP-47 tag for Intl.* formatters. Kept here so all date/number
// formatting in the app stays in lockstep with the selected UI language.
const LOCALE_TAG: Record<Locale, string> = {
    en: 'en-US',
    ka: 'ka-GE',
}

/**
 * Format a date in the current app locale. Pure function; pages pass
 * `prefs.locale` they already have from `usePreferences`.
 */
export function formatDate(value: string | Date, locale: Locale): string {
    const d = value instanceof Date ? value : new Date(value)
    return new Intl.DateTimeFormat(LOCALE_TAG[locale], {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(d)
}

/**
 * Format date+time in the current app locale.
 */
export function formatDateTime(value: string | Date, locale: Locale): string {
    const d = value instanceof Date ? value : new Date(value)
    return new Intl.DateTimeFormat(LOCALE_TAG[locale], {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d)
}

const TRANSLATIONS: Record<Locale, Record<string, string>> = {
    en: {
        // --- top-level & shared ---
        'app.title': 'CV Management',
        'search.placeholder': 'Search…',
        'attr.empty': 'Empty',
        'attr.lookup.prefix': 'Find by name…',
        'attr.lookup.category': 'Category',
        'attr.lookup.recent': 'Recently used',
        'nav.home': 'Home',
        'nav.positions': 'Positions',
        'nav.attributes': 'Attribute Library',
        'nav.profile': 'Profile',
        'nav.admin': 'Admin',
        'nav.login': 'Sign in',
        'nav.logout': 'Sign out',
        'nav.register': 'Sign up',
        'theme.light': 'Light',
        'theme.dark': 'Dark',
        'common.cancel': 'Cancel',
        'common.save': 'Save',
        'common.create': 'Create',
        'common.delete': 'Delete',
        'common.edit': 'Edit',
        'common.add': 'Add',
        'common.remove': 'Remove',
        'common.back': 'Back',

        // --- profile & sections ---
        'profile.me.title': 'About me',
        'profile.info.title': 'Information',
        'profile.projects.title': 'Projects',
        'profile.cvs.title': 'CVs',
        'profile.save': 'Save',
        'profile.saved': 'Saved.',
        'profile.conflict': 'This data was changed in another session. Reload to see the latest values.',

        // --- account type ---
        'account_type.choose': 'Choose your account type',
        'account_type.candidate': 'Candidate',
        'account_type.recruiter': 'Recruiter',
        'account_type.error.save': 'Could not save account type.',

        // --- attribute library ---
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

        // --- enum-style labels (internal values stay as-is; only the
        //     *UI label* is translated) ---
        'status.draft': 'Draft',
        'status.published': 'Published',
        'access.public': 'public',
        'access.restricted': 'restricted',
        'access.lost': 'lost',
        'role.candidate': 'Candidate',
        'role.recruiter': 'Recruiter',
        'role.admin': 'Admin',

        'level.all': 'All levels',
        'level.junior': 'Junior',
        'level.middle': 'Middle',
        'level.senior': 'Senior',
        'level.c_level': 'C-level',

        'data_type.string': 'String',
        'data_type.text': 'Text (Markdown)',
        'data_type.image': 'Image',
        'data_type.numeric': 'Numeric',
        'data_type.date': 'Date',
        'data_type.period': 'Period',
        'data_type.boolean': 'Boolean',
        'data_type.one_of_many': 'One of many',

        // Operator labels (displayed in the access-rule editor).
        // Symbol-only operators are kept as universal mathematical glyphs.
        'operator.eq': '=',
        'operator.ne': '≠',
        'operator.gt': '>',
        'operator.gte': '≥',
        'operator.lt': '<',
        'operator.lte': '≤',
        'operator.in': 'in',
        'operator.contains': 'contains',
        'operator.before': 'before',
        'operator.after': 'after',

        // --- layout chrome ---
        'layout.aria.language': 'Language',
        'layout.aria.theme': 'Theme',
        'layout.language.english': 'English',
        'layout.language.georgian': 'ქართული',

        // --- home / dashboard ---
        'home.title': 'Dashboard',
        'home.statistics': 'Statistics',
        'home.stat.cvs_24h': 'CVs (24h)',
        'home.stat.positions': 'Positions',
        'home.stat.candidates': 'Candidates',
        'home.stat.recruiters': 'Recruiters',
        'home.stat.published_cvs': 'Published CVs',
        'home.popular.title': 'Most popular positions (top 5)',
        'home.popular.col.position': 'Position',
        'home.popular.col.company': 'Company',
        'home.popular.col.level': 'Level',
        'home.popular.col.submitted_cvs': 'Submitted CVs',
        'home.popular.empty': 'No positions yet.',
        'home.latest.title': 'Latest positions',
        'home.latest.col.title': 'Title',
        'home.latest.col.company': 'Company',
        'home.latest.col.level': 'Level',
        'home.latest.col.updated': 'Updated',
        'home.latest.empty': 'No positions yet.',
        'home.tag_cloud.title': 'Tag cloud',
        'home.tag_cloud.empty': 'Tags appear once candidates create projects with technology tags.',

        // --- auth pages ---
        'auth.login.verified_banner': 'Email verified. You can sign in now.',
        'auth.login.verify_error_prefix': 'Verification link is invalid or expired:',
        'auth.login.invalid_credentials': 'Invalid credentials.',
        'auth.login.email_not_verified':
            'Please verify your email address before logging in. Check your inbox for the confirmation link.',
        'auth.login.forgot_link': 'Forgot password?',
        'auth.login.email_label': 'Email',
        'auth.login.password_label': 'Password',

        'auth.register.title': 'Sign up',
        'auth.register.email_label': 'Email',
        'auth.register.password_label': 'Password',
        'auth.register.account_type_label': 'Account type',
        'auth.register.submit': 'Sign up',
        'auth.register.failed': 'Registration failed.',
        // Shown on 409 from /api/registration. Points the user at
        // existing flows that can give them access without creating
        // a duplicate account. Phrased so it does NOT leak whether
        // they originally registered with email/password or via an
        // OAuth provider.
        'auth.register.duplicate_email_hint':
            'An account with this email already exists. Sign in with your existing provider, or use “Forgot password” to set a password.',

        'auth.forgot.title': 'Forgot password',
        'auth.forgot.email_label': 'Email',
        'auth.forgot.submit': 'Send reset link',
        'auth.forgot.failed': 'Could not send reset link. Try again.',
        'auth.forgot.success': 'If an account exists for that email, a reset link has been sent.',

        'auth.reset.title': 'Set a new password',
        'auth.reset.new_password_label': 'New password',
        'auth.reset.repeat_password_label': 'Repeat password',
        'auth.reset.submit': 'Reset password',
        'auth.reset.success': 'Password reset. Redirecting to sign in…',
        'auth.reset.missing_token': 'Missing or invalid reset link.',
        'auth.reset.password_too_short': 'Password must be at least 8 characters.',
        'auth.reset.passwords_mismatch': 'Passwords do not match.',
        'auth.reset.failed': 'Could not reset password.',
        'auth.reset.back_to_signin': 'Back to sign in',

        // --- set-password (authenticated; for OAuth-only users) ---
        // Mirrors the auth.reset.* validation copy where it overlaps
        // (length / confirmation messages), so both password-setting
        // flows feel like the same policy. Page-specific copy is here.
        'auth.set_password.title': 'Set a password',
        'auth.set_password.intro':
            'Add a password so you can also sign in with your email and password. You will still be able to sign in with your social account.',
        'auth.set_password.submit': 'Set password',
        'auth.set_password.success': 'Password set. Redirecting to your profile…',
        'auth.set_password.failed': 'Could not set password.',
        'auth.set_password.not_authenticated':
            'You need to be signed in to set a password.',
        'auth.set_password.oauth_only_hint':
            'Your account was created with a social provider. Set a password to enable email + password sign-in.',

        // --- auth status banner (non-401 /api/me failure) ---
        // Shown when the initial session check returned 5xx or never
        // reached the server. Distinct from the expected 401 path
        // (logged-out user), which keeps error=null and shows the
        // public UI normally.
        'auth.error.unable_to_verify':
            "We couldn't reach the authentication service. Some features may be unavailable.",

        // --- positions ---
        'positions.title': 'Positions',
        'positions.new_link': 'New position',
        'positions.filter.company_label': 'Company',
        'positions.filter.company_placeholder': 'Filter by company…',
        'positions.filter.level_label': 'Level',
        'positions.empty': 'No positions yet.',
        'positions.col.title': 'Title',
        'positions.col.company': 'Company',
        'positions.col.level': 'Level',
        'positions.col.access': 'Access',
        'positions.col.submitted_cvs': 'Submitted CVs',
        'positions.edit_selected': 'Edit selected',
        'positions.duplicate_selected': 'Duplicate selected',
        'positions.delete_selected': 'Delete selected',
        'positions.selected_count.one': '{count} selected',
        'positions.selected_count.other': '{count} selected',
        'positions.delete.confirm_one': 'Delete this position?',
        'positions.delete.confirm_other': 'Delete {count} positions?',

        // --- position editor ---
        'editor.title.new': 'New position',
        'editor.title.edit': 'Edit position',
        'editor.label.title': 'Title',
        'editor.label.company': 'Company',
        'editor.label.short_description': 'Short description',
        'editor.label.level': 'Level',
        'editor.label.max_projects': 'Max projects',
        'editor.label.public_switch': 'Public (anyone can build CV)',
        'editor.label.project_tag_filter': 'Project tag filter',
        'editor.placeholder.project_tag_filter': 'e.g. python, sql',
        'editor.attributes.title': 'Attributes',
        'editor.attributes.empty': 'No attributes yet.',
        'editor.attribute_library.title': 'Attribute library',
        'editor.access.title': 'Access rules',
        'editor.access.add_rule': 'Add rule',
        'editor.access.empty': 'No rules yet. Without rules, restricted positions would block everyone.',
        'editor.save': 'Save',
        'editor.cancel': 'Cancel',
        'editor.save.conflict': 'Position was modified by another session. Reload to see the latest changes before saving again.',
        'editor.save.failed': 'Failed to save.',

        // --- position view ---
        'view.tab.details': 'Details',
        'view.tab.discussion': 'Discussion',
        'view.build_cv': 'Build CV',
        'view.browse_cvs': 'Browse CVs',
        'view.edit': 'Edit',
        'view.max_projects': 'Max projects in CV:',
        'view.project_tags': 'Project tags:',
        'view.attributes.title': 'Attributes',
        'view.attributes.empty': 'No attributes selected.',
        'view.access.title': 'Access rules',
        'view.access.warning': "You don't currently satisfy this position's access rules.",
        'info.remove_selected': 'Remove selected',
        'view.error.build_failed': 'Failed to build CV.',

        // --- position cvs ---
        'cv_position.title': 'CVs — {title}',
        'cv_position.empty': 'No published CVs yet for this position.',
        'cv_position.preview_selected': 'Preview selected',
        'cv_position.col.candidate': 'Candidate',
        'cv_position.col.likes': 'Likes',
        'cv_position.col.published': 'Published',
        'cv_position.col.empty_attrs': 'Empty attrs',
        'cv_position.preview.projects': 'Projects',
        'cv_position.error.only_recruiters': 'Only recruiters may browse CVs.',
        'cv_position.attr.empty_label': 'Empty',

        // --- cv detail ---
        'cv_detail.error.lost_access': 'You have lost access to this position.',
        'cv_detail.error.not_found': 'CV not found.',
        'cv_detail.error.generic': 'Could not load CV.',
        'cv_detail.action.publish': 'Publish',
        'cv_detail.action.delete': 'Delete',
        'cv_detail.action.delete_confirm': 'Delete this CV?',
        'cv_detail.action.like': '☆ Like',
        'cv_detail.action.liked': '★ Liked',
        'cv_detail.action.edit_attributes': 'Edit attributes',
        'cv_detail.action.done_editing': 'Done editing',
        'cv_detail.warn.required_missing': 'Some required attributes are missing. Complete them before publishing this CV.',
        'cv_detail.about': 'About the candidate',
        'cv_detail.edit_profile': 'Edit profile',
        'cv_detail.attributes.title': 'Attributes',
        'cv_detail.attributes.col.attribute': 'Attribute',
        'cv_detail.attributes.col.value': 'Value',
        'cv_detail.attributes.empty': 'No attributes configured for this position.',
        'cv_detail.attributes.edit_hint.zero': 'Select one row to edit it.',
        'cv_detail.attributes.edit_hint.one': '1 selected.',
        'cv_detail.attributes.edit_hint.other': '{count} selected — edit one at a time.',
        'cv_detail.attributes.clear': 'Clear',
        'cv_detail.attributes.edit_selected': 'Edit selected',
        'cv_detail.attributes.aria.select': 'Select',
        'cv_detail.projects.title': 'Projects',
        'cv_detail.projects.empty': 'No matching projects.',
        'cv_detail.projects.add_link': 'Add some in your profile',
        'cv_detail.projects.candidate_empty': 'The candidate has no projects that match this position.',
        'cv_detail.projects.col.project': 'Project',
        'cv_detail.projects.col.technologies': 'Technologies',
        'cv_detail.projects.col.period': 'Period',
        'cv_detail.attr_value.not_specified': 'Not specified',
        'cv_detail.attr_value.yes': 'Yes',
        'cv_detail.attr_value.no': 'No',
        'cv_detail.attr_value.modal_aria_close': 'Close',
        'cv_detail.attr_value.modal_cancel': 'Cancel',
        'cv_detail.attr_value.modal_save': 'Save',
        'cv_detail.save.conflict': 'This value was changed in another session. Please reload.',
        'cv_detail.save.failed': 'Save failed.',
        'cv_detail.publish.failed': 'Publish failed.',

        // --- admin ---
        'admin.forbidden': 'Forbidden.',
        'admin.col.email': 'Email',
        'admin.col.role': 'Role',
        'admin.col.verified': 'Verified',
        'admin.col.blocked': 'Blocked',
        'admin.block_selected': 'Block selected',
        'admin.unblock_selected': 'Unblock selected',
        'admin.promote_admin': 'Promote to admin',
        'admin.set_recruiter': 'Set as recruiter',
        'admin.set_candidate': 'Set as candidate',
        'admin.delete_selected': 'Delete selected',
        'admin.selected_count.one': '{count} selected',
        'admin.selected_count.other': '{count} selected',
        'admin.delete.confirm_one': 'Delete this user?',
        'admin.delete.confirm_other': 'Delete {count} users?',
        'admin.total': 'Total: {count}',

        // --- cv/profile CV list ---
        'cvs.empty': 'No CVs yet. Open a position and click Build CV.',
        'cvs.col.position': 'Position',
        'cvs.col.status': 'Status',
        'cvs.col.published': 'Published',
        'cvs.col.updated': 'Updated',
        'cvs.col.likes': 'Likes',
        'cvs.col.access': 'Access',

        // --- profile sections ---
        'me.label.first_name': 'First name',
        'me.label.last_name': 'Last name',
        'me.label.location': 'Location',
        'me.status.saving': 'Saving…',
        'me.status.saved': 'Saved',

        'info.placeholder.cloudinary': 'https://res.cloudinary.com/…',

        'projects.section.title': 'Projects',
        'projects.section.new': 'New project',
        'projects.section.loading': 'Loading…',
        'projects.section.empty': 'No projects yet.',
        'projects.section.col.name': 'Name',
        'projects.section.col.period': 'Period',
        'projects.section.col.tags': 'Tags',
        'projects.section.col.description': 'Description (Markdown)',
        'projects.section.label.name': 'Name',
        'projects.section.label.period_start': 'Period start',
        'projects.section.label.period_end': 'Period end',
        'projects.section.label.tags': 'Tags',
        'projects.section.label.description': 'Description (Markdown)',
        'projects.section.save': 'Save',
        'projects.section.save_failed': 'Failed to save.',
        'projects.section.delete.confirm_one': 'Delete this project?',
        'projects.section.delete.confirm_other': 'Delete {count} projects?',

        'info.status.saving': 'Saving…',
        'info.status.saved': 'Saved',
        'info.status.not_saved': 'Not saved yet — pick a value.',
        'info.picker_hint': 'Add some from the picker on the right.',
        'info.label.from': 'From',
        'info.selection_hint.zero': 'Select rows to enable actions.',
        'info.selection_hint.one': '{count} selected.',
        'info.selection_hint.other': '{count} selected.',
        'info.label.to': 'To',
        'info.delete.confirm_one': 'Remove this attribute from your profile?',
        'info.delete.confirm_other': 'Remove {count} attributes from your profile?',

        // --- misc components ---
        'discussion.empty': 'No posts yet.',
        'discussion.label.new_post': 'New post (Markdown)',
        'tag_input.placeholder': 'Add tag…',
        'tag_input.aria.remove': 'Remove',

        // --- generic errors ---
        'error.generic': 'Something went wrong. Please try again.',
        'error.network': 'Network error. Please check your connection.',
    },
    ka: {
        'app.title': 'CV მართვის სისტემა',
        'search.placeholder': 'ძიება…',
        'attr.empty': 'ცარიელია',
        'attr.lookup.prefix': 'ძებნა სახელით…',
        'attr.lookup.category': 'კატეგორია',
        'attr.lookup.recent': 'ბოლო დროს გამოყენებული',
        'nav.home': 'მთავარი',
        'nav.positions': 'პოზიციები',
        'nav.attributes': 'ატრიბუტების ბიბლიოთეკა',
        'nav.profile': 'პროფილი',
        'nav.admin': 'ადმინი',
        'nav.login': 'შესვლა',
        'nav.logout': 'გასვლა',
        'nav.register': 'რეგისტრაცია',
        'theme.light': 'ღია',
        'theme.dark': 'მუქი',
        'common.cancel': 'გაუქმება',
        'common.save': 'შენახვა',
        'common.create': 'შექმნა',
        'common.delete': 'წაშლა',
        'common.edit': 'რედაქტირება',
        'common.add': 'დამატება',
        'common.remove': 'წაშლა',
        'common.back': 'უკან',

        'profile.me.title': 'ჩემს შესახებ',
        'profile.info.title': 'ინფორმაცია',
        'profile.projects.title': 'პროექტები',
        'profile.cvs.title': 'CV-ები',
        'profile.save': 'შენახვა',
        'profile.saved': 'შენახულია.',
        'profile.conflict': 'ეს მონაცემები სხვა სესიაში შეიცვალა. განაახლეთ გვერდი უახლესი მნიშვნელობების სანახავად.',

        'account_type.choose': 'აირჩიეთ პროფილის ტიპი',
        'account_type.candidate': 'კანდიდატი',
        'account_type.recruiter': 'რეკრუტერი',
        'account_type.error.save': 'პროფილის ტიპი ვერ შევინახე.',

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

        'status.draft': 'მონახაზი',
        'status.published': 'გამოქვეყნებული',
        'access.public': 'საჯარო',
        'access.restricted': 'შეზღუდული',
        'access.lost': 'დაკარგული',
        'role.candidate': 'კანდიდატი',
        'role.recruiter': 'რეკრუტერი',
        'role.admin': 'ადმინი',

        'level.all': 'ყველა დონე',
        'level.junior': 'ჯუნიორი',
        'level.middle': 'მიდლი',
        'level.senior': 'სენიორი',
        'level.c_level': 'C-დონე',

        'data_type.string': 'სტრინგი',
        'data_type.text': 'ტექსტი (Markdown)',
        'data_type.image': 'სურათი',
        'data_type.numeric': 'რიცხვი',
        'data_type.date': 'თარიღი',
        'data_type.period': 'პერიოდი',
        'data_type.boolean': 'ლოგიკური',
        'data_type.one_of_many': 'ერთი მრავალიდან',

        'operator.eq': '=',
        'operator.ne': '≠',
        'operator.gt': '>',
        'operator.gte': '≥',
        'operator.lt': '<',
        'operator.lte': '≤',
        'operator.in': 'ში',
        'operator.contains': 'შეიცავს',
        'operator.before': 'მდე',
        'operator.after': 'შემდეგ',

        'layout.aria.language': 'ენა',
        'layout.aria.theme': 'თემა',
        'layout.language.english': 'English',
        'layout.language.georgian': 'ქართული',

        'home.title': 'დაფა',
        'home.statistics': 'სტატისტიკა',
        'home.stat.cvs_24h': 'CV-ები (24 სთ)',
        'home.stat.positions': 'პოზიციები',
        'home.stat.candidates': 'კანდიდატები',
        'home.stat.recruiters': 'რეკრუტერები',
        'home.stat.published_cvs': 'გამოქვეყნებული CV-ები',
        'home.popular.title': 'ყველაზე პოპულარული პოზიციები (ტოპ 5)',
        'home.popular.col.position': 'პოზიცია',
        'home.popular.col.company': 'კომპანია',
        'home.popular.col.level': 'დონე',
        'home.popular.col.submitted_cvs': 'გაგზავნილი CV-ები',
        'home.popular.empty': 'პოზიციები ჯერ არ არის.',
        'home.latest.title': 'ბოლო პოზიციები',
        'home.latest.col.title': 'სათაური',
        'home.latest.col.company': 'კომპანია',
        'home.latest.col.level': 'დონე',
        'home.latest.col.updated': 'განახლებულია',
        'home.latest.empty': 'პოზიციები ჯერ არ არის.',
        'home.tag_cloud.title': 'ტეგების ღრუბელი',
        'home.tag_cloud.empty': 'ტეგები გამოჩნდება მაშინ, როცა კანდიდატები ტექნოლოგიის ტეგებით პროექტებს შექმნიან.',

        'auth.login.verified_banner': 'ელ-ფოსტა დადასტურებულია. ახლა შეგიძლიათ შესვლა.',
        'auth.login.verify_error_prefix': 'დადასტურების ბმული არასწორია ან ვადაგასდგომია:',
        'auth.login.invalid_credentials': 'არასწორი მონაცემები.',
        'auth.login.email_not_verified':
            'გთხოვთ, დაადასტუროთ ელ-ფოსტა შესვლამდე. შეამოწმეთ შემოსული წერილი დადასტურების ბმულზე.',
        'auth.login.forgot_link': 'დაგავიწყდათ პაროლი?',
        'auth.login.email_label': 'ელ-ფოსტა',
        'auth.login.password_label': 'პაროლი',

        'auth.register.title': 'რეგისტრაცია',
        'auth.register.email_label': 'ელ-ფოსტა',
        'auth.register.password_label': 'პაროლი',
        'auth.register.account_type_label': 'ანგარიშის ტიპი',
        'auth.register.submit': 'რეგისტრაცია',
        'auth.register.failed': 'რეგისტრაცია ვერ მოხერხდა.',
        'auth.register.duplicate_email_hint':
            'ამ ელ-ფოსტით ანგარიში უკვე არსებობს. შედით თქვენი არსებული პროვაიდერით, ან გამოიყენეთ „პაროლის აღდგენა" პაროლის დასაყენებლად.',

        'auth.forgot.title': 'პაროლის აღდგენა',
        'auth.forgot.email_label': 'ელ-ფოსტა',
        'auth.forgot.submit': 'ბმულის გამოგზავნა',
        'auth.forgot.failed': 'ბმულის გამოგზავნა ვერ მოხერხდა. სცადეთ თავიდან.',
        'auth.forgot.success': 'თუ ამ ელ-ფოსტით ანგარიში არსებობს, აღდგენის ბმული გამოგზავნილია.',

        'auth.reset.title': 'დააყენეთ ახალი პაროლი',
        'auth.reset.new_password_label': 'ახალი პაროლი',
        'auth.reset.repeat_password_label': 'გაიმეორეთ პაროლი',
        'auth.reset.submit': 'პაროლის აღდგენა',
        'auth.reset.success': 'პაროლი აღდგენილია. გადამისამართება შესვლაზე…',
        'auth.reset.missing_token': 'აღდგენის ბმული არ არსებობს ან არასწორია.',
        'auth.reset.password_too_short': 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს.',
        'auth.reset.passwords_mismatch': 'პაროლები არ ემთხვევა.',
        'auth.reset.failed': 'პაროლის აღდგენა ვერ მოხერხდა.',
        'auth.reset.back_to_signin': 'შესვლაზე დაბრუნება',

        // --- set-password (authenticated; for OAuth-only users) ---
        'auth.set_password.title': 'პაროლის დაყენება',
        'auth.set_password.intro':
            'დაამატეთ პაროლი, რომ შეძლოთ შესვლა ელ-ფოსტითა და პაროლით. სოციალური ანგარიშით შესვლაც კვლავ შეგიძლიათ.',
        'auth.set_password.submit': 'პაროლის დაყენება',
        'auth.set_password.success': 'პაროლი დაყენებულია. გადამისამართება პროფილზე…',
        'auth.set_password.failed': 'პაროლის დაყენება ვერ მოხერხდა.',
        'auth.set_password.not_authenticated':
            'პაროლის დასაყენებლად უნდა იყოთ შესული.',
        'auth.set_password.oauth_only_hint':
            'თქვენი ანგარიში შეიქმნა სოციალური პროვაიდერით. დააყენეთ პაროლი, რომ ელ-ფოსტით შესვლაც შეძლოთ.',

        // --- auth status banner (non-401 /api/me failure) ---
        'auth.error.unable_to_verify':
            'ავთენტიფიკაციის სერვისთან დაკავშირება ვერ მოხერხდა. ზოგიერთი ფუნქცია შეიძლება მიუწვდომელი იყოს.',

        'positions.title': 'პოზიციები',
        'positions.new_link': 'ახალი პოზიცია',
        'positions.filter.company_label': 'კომპანია',
        'positions.filter.company_placeholder': 'კომპანიით გაფილტვრა…',
        'positions.filter.level_label': 'დონე',
        'positions.empty': 'პოზიციები ჯერ არ არის.',
        'positions.col.title': 'სათაური',
        'positions.col.company': 'კომპანია',
        'positions.col.level': 'დონე',
        'positions.col.access': 'წვდომა',
        'positions.col.submitted_cvs': 'გაგზავნილი CV-ები',
        'positions.edit_selected': 'არჩეულის რედაქტირება',
        'positions.duplicate_selected': 'არჩეულის კოპირება',
        'positions.delete_selected': 'არჩეულის წაშლა',
        'positions.selected_count.one': 'არჩეულია {count}',
        'positions.selected_count.other': 'არჩეულია {count}',
        'positions.delete.confirm_one': 'წავშალო ეს პოზიცია?',
        'positions.delete.confirm_other': 'წავშალო {count} პოზიცია?',

        'editor.title.new': 'ახალი პოზიცია',
        'editor.title.edit': 'პოზიციის რედაქტირება',
        'editor.label.title': 'სათაური',
        'editor.label.company': 'კომპანია',
        'editor.label.short_description': 'მოკლე აღწერა',
        'editor.label.level': 'დონე',
        'editor.label.max_projects': 'მაქსიმალური პროექტები',
        'editor.label.public_switch': 'საჯარო (ნებისმიერს შეუძლია CV-ის შექმნა)',
        'editor.label.project_tag_filter': 'პროექტის ტეგების ფილტრი',
        'editor.placeholder.project_tag_filter': 'მაგ. python, sql',
        'editor.attributes.title': 'ატრიბუტები',
        'editor.attributes.empty': 'ატრიბუტები ჯერ არ არის.',
        'editor.attribute_library.title': 'ატრიბუტების ბიბლიოთეკა',
        'editor.access.title': 'წვდომის წესები',
        'editor.access.add_rule': 'წესის დამატება',
        'editor.access.empty': 'წესები ჯერ არ არის. წესების გარეშე შეზღუდული პოზიცია ყველას დაბლოკავს.',
        'editor.save': 'შენახვა',
        'editor.cancel': 'გაუქმება',
        'editor.save.conflict': 'პოზიცია სხვა სესიაში შეიცვალა. განაახლეთ გვერდი უახლესი ცვლილებების სანახავად და შემდეგ სცადეთ თავიდან.',
        'editor.save.failed': 'შენახვა ვერ მოხერხდა.',

        'view.tab.details': 'დეტალები',
        'view.tab.discussion': 'განხილვა',
        'view.build_cv': 'CV-ის შექმნა',
        'view.browse_cvs': 'CV-ების ნახვა',
        'view.edit': 'რედაქტირება',
        'view.max_projects': 'CV-ში მაქსიმალური პროექტები:',
        'view.project_tags': 'პროექტის ტეგები:',
        'view.attributes.title': 'ატრიბუტები',
        'view.attributes.empty': 'ატრიბუტები არ არის არჩეული.',
        'view.access.title': 'წვდომის წესები',
        'view.access.warning': 'ამ პოზიციის წვდომის წესებს ჯერჯერობით ვერ აკმაყოფილებთ.',
        'view.error.build_failed': 'CV-ის შექმნა ვერ მოხერხდა.',

        'cv_position.title': 'CV-ები — {title}',
        'cv_position.empty': 'ამ პოზიციისთვის ჯერ არ არის გამოქვეყნებული CV.',
        'cv_position.preview_selected': 'არჩეულის გადახედვა',
        'cv_position.col.candidate': 'კანდიდატი',
        'cv_position.col.likes': 'მოწონებები',
        'cv_position.col.published': 'გამოქვეყნებულია',
        'cv_position.col.empty_attrs': 'ცარიელი ატრიბ.',
        'cv_position.preview.projects': 'პროექტები',
        'cv_position.error.only_recruiters': 'CV-ების ნახვა მხოლოდ რეკრუტერებს შეუძლიათ.',
        'cv_position.attr.empty_label': 'ცარიელია',

        'cv_detail.error.lost_access': 'პოზიციაზე წვდომა დაკარგეს.',
        'cv_detail.error.not_found': 'CV ვერ მოიძებნა.',
        'cv_detail.error.generic': 'CV-ის ჩატვირთვა ვერ მოხერხდა.',
        'cv_detail.action.publish': 'გამოქვეყნება',
        'cv_detail.action.delete': 'წაშლა',
        'cv_detail.action.delete_confirm': 'წავშალო ეს CV?',
        'cv_detail.action.like': '☆ მოწონება',
        'cv_detail.action.liked': '★ მოწონებულია',
        'cv_detail.action.edit_attributes': 'ატრიბუტების რედაქტირება',
        'cv_detail.action.done_editing': 'რედაქტირების დასრულება',
        'cv_detail.warn.required_missing': 'ზოგიერთი სავალდებულო ატრიბუტი აკლია. შეავსეთ ისინი CV-ის გამოქვეყნებამდე.',
        'cv_detail.about': 'კანდიდატის შესახებ',
        'cv_detail.edit_profile': 'პროფილის რედაქტირება',
        'cv_detail.attributes.title': 'ატრიბუტები',
        'cv_detail.attributes.col.attribute': 'ატრიბუტი',
        'cv_detail.attributes.col.value': 'მნიშვნელობა',
        'cv_detail.attributes.empty': 'ამ პოზიციაზე ატრიბუტები არ არის კონფიგურირებული.',
        'cv_detail.attributes.edit_hint.zero': 'შერჩეთ ერთი სტრიქონი რედაქტირებისთვის.',
        'cv_detail.attributes.edit_hint.one': 'არჩეულია 1.',
        'cv_detail.attributes.edit_hint.other': 'არჩეულია {count} — რედაქტირება ერთდროულად ერთზე.',
        'cv_detail.attributes.clear': 'გასუფთავება',
        'cv_detail.attributes.edit_selected': 'არჩეულის რედაქტირება',
        'cv_detail.attributes.aria.select': 'შერჩევა',
        'cv_detail.projects.title': 'პროექტები',
        'cv_detail.projects.empty': 'შესაბამისი პროექტები არ მოიძებნა.',
        'cv_detail.projects.add_link': 'დაამატეთ პროფილში',
        'cv_detail.projects.candidate_empty': 'კანდიდატს ამ პოზიციის შესაბამისი პროექტები არ აქვს.',
        'cv_detail.projects.col.project': 'პროექტი',
        'cv_detail.projects.col.technologies': 'ტექნოლოგიები',
        'cv_detail.projects.col.period': 'პერიოდი',
        'cv_detail.attr_value.not_specified': 'მითითებული არ არის',
        'cv_detail.attr_value.yes': 'დიახ',
        'cv_detail.attr_value.no': 'არა',
        'cv_detail.attr_value.modal_aria_close': 'დახურვა',
        'cv_detail.attr_value.modal_cancel': 'გაუქმება',
        'cv_detail.attr_value.modal_save': 'შენახვა',
        'cv_detail.save.conflict': 'ეს მნიშვნელობა სხვა სესიაში შეიცვალა. განაახლეთ გვერდი.',
        'cv_detail.save.failed': 'შენახვა ვერ მოხერხდა.',
        'cv_detail.publish.failed': 'გამოქვეყნება ვერ მოხერხდა.',

        'admin.forbidden': 'წვდომა აკრძალულია.',
        'admin.col.email': 'ელ-ფოსტა',
        'admin.col.role': 'როლი',
        'admin.col.verified': 'დადასტურებულია',
        'admin.col.blocked': 'დაბლოკილია',
        'admin.block_selected': 'არჩეულების დაბლოკვა',
        'admin.unblock_selected': 'ბლოკის მოხსნა',
        'admin.promote_admin': 'ადმინად დაწინაურება',
        'admin.set_recruiter': 'რეკრუტერად დაყენება',
        'admin.set_candidate': 'კანდიდატად დაყენება',
        'admin.delete_selected': 'არჩეულების წაშლა',
        'admin.selected_count.one': 'არჩეულია {count}',
        'admin.selected_count.other': 'არჩეულია {count}',
        'admin.delete.confirm_one': 'წავშალო ეს მომხმარებელი?',
        'admin.delete.confirm_other': 'წავშალო {count} მომხმარებელი?',
        'admin.total': 'სულ: {count}',

        'cvs.empty': 'CV-ები ჯერ არ არის. გახსენით პოზიცია და დააჭირეთ CV-ის შექმნას.',
        'cvs.col.position': 'პოზიცია',
        'cvs.col.status': 'სტატუსი',
        'cvs.col.published': 'გამოქვეყნებულია',
        'cvs.col.updated': 'განახლებულია',
        'cvs.col.likes': 'მოწონებები',
        'cvs.col.access': 'წვდომა',

        'me.label.first_name': 'სახელი',
        'me.label.last_name': 'გვარი',
        'me.label.location': 'მდებარეობა',
        'me.status.saving': 'ინახება…',
        'me.status.saved': 'შენახულია',

        'info.placeholder.cloudinary': 'https://res.cloudinary.com/…',

        'projects.section.title': 'პროექტები',
        'projects.section.new': 'ახალი პროექტი',
        'projects.section.loading': 'იტვირთება…',
        'projects.section.empty': 'პროექტები ჯერ არ არის.',
        'projects.section.col.name': 'სახელი',
        'projects.section.col.period': 'პერიოდი',
        'projects.section.col.tags': 'ტეგები',
        'projects.section.col.description': 'აღწერა (Markdown)',
        'projects.section.label.name': 'სახელი',
        'projects.section.label.period_start': 'პერიოდის დასაწყისი',
        'projects.section.label.period_end': 'პერიოდის დასასრული',
        'projects.section.label.tags': 'ტეგები',
        'projects.section.label.description': 'აღწერა (Markdown)',
        'projects.section.save': 'შენახვა',
        'projects.section.save_failed': 'შენახვა ვერ მოხერხდა.',
        'projects.section.delete.confirm_one': 'წავშალო ეს პროექტი?',
        'projects.section.delete.confirm_other': 'წავშალო {count} პროექტი?',

        'info.status.saving': 'ინახება…',
        'info.status.saved': 'შენახულია',
        'info.status.not_saved': 'ჯერ არ შენახულა — აირჩიეთ მნიშვნელობა.',
        'info.picker_hint': 'დაამატეთ რამდენიმე მარჯვნიდან.',
        'info.label.from': 'დან',
        'info.remove_selected': 'არჩეულების წაშლა',
        'info.selection_hint.zero': 'მოქმედებების გამოსაყენებლად შერჩეთ სტრიქონები.',
        'info.selection_hint.one': 'არჩეულია {count}.',
        'info.selection_hint.other': 'არჩეულია {count}.',
        'info.label.to': 'მდე',
        'info.delete.confirm_one': 'წავშალო ეს ატრიბუტი თქვენი პროფილიდან?',
        'info.delete.confirm_other': 'წავშალო {count} ატრიბუტი თქვენი პროფილიდან?',

        'discussion.empty': 'პოსტები ჯერ არ არის.',
        'discussion.label.new_post': 'ახალი პოსტი (Markdown)',
        'tag_input.placeholder': 'ტეგის დამატება…',
        'tag_input.aria.remove': 'წაშლა',

        'error.generic': 'რაღაც არასწორად წავიდა. გთხოვთ სცადოთ თავიდან.',
        'error.network': 'ქსელის შეცდომა. შეამოწმეთ კავშირი.',
    },
}

export interface TranslationHelpers {
    /** Like `t(key)`, but substitutes `{name}` placeholders with vars. */
    tArg: (key: string, vars?: Record<string, string | number>) => string
    /**
     * Plural-aware lookup. Picks `.one` for count === 1, else `.other`.
     * Substitute `{count}` (or any var) into the chosen template.
     */
    tPlural: (
        key: string,
        count: number,
        vars?: Record<string, string | number>,
    ) => string
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

    const tArg = (key: string, vars?: Record<string, string | number>): string => {
        const base = t(key)
        if (vars === undefined) return base
        return Object.entries(vars).reduce(
            (acc, [k, v]) => acc.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
            base,
        )
    }

    const tPlural = (
        key: string,
        count: number,
        vars?: Record<string, string | number>,
    ): string => tArg(`${key}${count === 1 ? '.one' : '.other'}`, vars ?? { count })

    return (
        <AppPreferencesContext.Provider
            value={{ ...prefs, setLocale, setTheme }}
        >
            <I18nContext.Provider
                value={{ t, tArg, tPlural }}
            >
                {children}
            </I18nContext.Provider>
        </AppPreferencesContext.Provider>
    )
}

interface I18nContextValue extends TranslationHelpers {
    t: (key: string) => string
}

const I18nContext = createContext<I18nContextValue>({
    t: (k: string) => k,
    tArg: (k: string) => k,
    tPlural: (k: string) => k,
})

/**
 * Returns the basic `t(key)` function.
 * Kept as-is (and unchanged in signature) so existing call sites continue
 * to work with `const t = useTranslation(); t('foo.bar')`.
 */
export function useTranslation(): (key: string) => string {
    return useContext(I18nContext).t
}

/**
 * Returns the full set of translation helpers (`t`, `tArg`, `tPlural`).
 * Use this when you need interpolation or plural-aware messages.
 */
export function useT(): I18nContextValue {
    return useContext(I18nContext)
}

export function usePreferences(): PreferencesState {
    const ctx = useContext(AppPreferencesContext)
    if (ctx === null) throw new Error('usePreferences must be inside provider')
    return ctx
}
